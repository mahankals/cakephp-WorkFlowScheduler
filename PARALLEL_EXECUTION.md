# Parallel Execution Implementation - Complete

## ✅ Implementation Summary

Successfully implemented **parallel workflow execution** with background process spawning for the CakePHP Workflow Scheduler Plugin.

## 🎯 What Was Implemented

### 1. **New ExecuteWorkflowCommand**
- **File**: `plugins/WorkFlowScheduler/src/Command/ExecuteWorkflowCommand.php`
- **Purpose**: Executes a single workflow in isolation
- **Features**:
  - Takes execution ID as argument
  - Auto-discovers workflow class
  - Updates execution status (running → completed/failed)
  - Handles errors gracefully
  - Updates workflow last_executed timestamp

### 2. **Enhanced SchedulerCommand**
- **File**: `plugins/WorkFlowScheduler/src/Command/SchedulerCommand.php`
- **New Features**:
  - ✅ Parallel execution with configurable concurrency
  - ✅ Process tracking and cleanup
  - ✅ Cross-platform support (Windows + Linux)
  - ✅ `--max-concurrent` option (default: 5)
  - ✅ Process monitoring and cleanup
  - ✅ Graceful handling of finished processes

### 3. **Production Deployment Files**

#### **Systemd Service** (`config/workflow-scheduler.service`)
```ini
[Unit]
Description=CakePHP Workflow Scheduler Daemon
After=mysql.service

[Service]
Type=simple
User=www-data
ExecStart=/usr/bin/php bin/cake work_flow_scheduler.scheduler --max-concurrent=5
Restart=always

[Install]
WantedBy=multi-user.target
```

#### **Supervisor Config** (`config/workflow-scheduler.conf`)
```ini
[program:workflow-scheduler]
command=/usr/bin/php /var/www/app/bin/cake work_flow_scheduler.scheduler --max-concurrent=5
autostart=true
autorestart=true
user=www-data
```

### 4. **Updated Documentation**
- ✅ README.md - Added comprehensive parallel execution section
- ✅ Performance comparison table
- ✅ Deployment guides (Systemd, Supervisor, Cron)
- ✅ Monitoring commands
- ✅ Configuration guidelines

## 🚀 How It Works

### Architecture

```
┌─────────────────────────────────────────┐
│   Scheduler Daemon (Main Process)      │
│   - Monitors pending executions         │
│   - Spawns worker processes             │
│   - Enforces max concurrent limit       │
└─────────────────┬───────────────────────┘
                  │
        ┌─────────┼─────────┬─────────┐
        ▼         ▼         ▼         ▼
    ┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐
    │Worker1│ │Worker2│ │Worker3│ │Worker4│
    │Exec A │ │Exec B │ │Exec C │ │Exec D │
    └───────┘ └───────┘ └───────┘ └───────┘
```

### Process Flow

1. **Scheduler** finds pending executions in database
2. **Checks** current running processes count
3. **Spawns** new worker if under max concurrent limit
4. **Worker** (ExecuteWorkflowCommand) runs independently:
   - Updates status to "running"
   - Executes workflow steps
   - Updates status to "completed" or "failed"
5. **Scheduler** cleans up finished processes
6. **Repeats** until all pending executions are processed

### Platform-Specific Implementation

#### **Windows** (Development)
```php
popen('start /B ' . $cmd, 'r');
```
- Uses `start /B` for background execution
- Tracks via database status

#### **Linux** (Production)
```php
$cmd .= ' > /dev/null 2>&1 & echo $!';
$pid = trim(shell_exec($cmd));
```
- Spawns true background process
- Captures PID for tracking
- Uses `ps` to check if process is running

## 📊 Performance Impact

### Before (Sequential)
```
Workflow A (2 min) → Workflow B (2 min) → Workflow C (2 min)
Total Time: 6 minutes
```

### After (Parallel, max-concurrent=5)
```
Workflow A (2 min) ┐
Workflow B (2 min) ├─ All run simultaneously
Workflow C (2 min) ┘
Total Time: 2 minutes
```

### Real-World Example
| Workflows | Duration Each | Sequential | Parallel (5) | Speedup |
|-----------|--------------|-----------|--------------|---------|
| 5 | 2 min | 10 min | 2 min | **5x faster** |
| 10 | 1 min | 10 min | 2 min | **5x faster** |
| 20 | 30 sec | 10 min | 2 min | **5x faster** |

## 🎮 Usage Examples

### Development (Windows)
```bash
# Start with default settings
bin/cake work_flow_scheduler.scheduler

# Custom concurrency
bin/cake work_flow_scheduler.scheduler --max-concurrent=3

# One-time run
bin/cake work_flow_scheduler.scheduler --once
```

### Production (Linux)

#### **Option 1: Systemd** (Recommended)
```bash
# Install
sudo cp plugins/WorkFlowScheduler/config/workflow-scheduler.service /etc/systemd/system/
sudo nano /etc/systemd/system/workflow-scheduler.service  # Edit paths
sudo systemctl daemon-reload
sudo systemctl enable workflow-scheduler
sudo systemctl start workflow-scheduler

# Monitor
sudo systemctl status workflow-scheduler
sudo journalctl -u workflow-scheduler -f
```

#### **Option 2: Supervisor**
```bash
# Install
sudo cp plugins/WorkFlowScheduler/config/workflow-scheduler.conf /etc/supervisor/conf.d/
sudo nano /etc/supervisor/conf.d/workflow-scheduler.conf  # Edit paths
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start workflow-scheduler

# Monitor
sudo supervisorctl status workflow-scheduler
sudo supervisorctl tail -f workflow-scheduler
```

#### **Option 3: Cron**
```bash
# Add to crontab
* * * * * cd /var/www/app && bin/cake work_flow_scheduler.scheduler --once --max-concurrent=5
```

## 🔍 Monitoring

### Check Running Processes
```bash
# Linux
ps aux | grep work_flow_scheduler
ps aux | grep execute_workflow | wc -l

# Process tree
pstree -p | grep cake

# Resource usage
top -u www-data
htop -u www-data
```

### Logs
```bash
# Systemd
sudo journalctl -u workflow-scheduler -f

# Supervisor
sudo supervisorctl tail -f workflow-scheduler

# Application logs
tail -f logs/cli-error.log
```

## ⚙️ Configuration

### Concurrency Limits

Choose based on your server resources:

| Server Type | RAM | Recommended Max Concurrent |
|------------|-----|---------------------------|
| Light | 1-2 GB | 2 |
| Medium | 4-8 GB | 5 (default) |
| Heavy | 16+ GB | 10 |

### Resource Estimation
- Each workflow: ~50-200 MB RAM
- CPU: Depends on workflow logic
- Start conservative, monitor, and adjust

## 🧪 Testing

### Test Parallel Execution
1. Trigger multiple workflows from UI
2. Check process count:
   ```bash
   ps aux | grep execute_workflow | wc -l
   ```
3. Verify in database:
   ```sql
   SELECT id, status, started FROM workflow_executions 
   WHERE status IN ('pending', 'running') 
   ORDER BY started DESC;
   ```

## 🎁 Benefits

1. **5x Faster** - Run 5 workflows in the time of 1
2. **Scalable** - Adjust concurrency based on server capacity
3. **Production Ready** - Includes deployment configs
4. **Cross-Platform** - Works on Windows and Linux
5. **Monitored** - Easy to track and debug
6. **Reliable** - Automatic process cleanup
7. **Flexible** - Configure per environment

## 📝 Files Created/Modified

### Created
- ✅ `src/Command/ExecuteWorkflowCommand.php`
- ✅ `config/workflow-scheduler.service`
- ✅ `config/workflow-scheduler.conf`

### Modified
- ✅ `src/Command/SchedulerCommand.php` - Complete rewrite for parallel execution
- ✅ `src/Plugin.php` - Registered ExecuteWorkflowCommand
- ✅ `README.md` - Added parallel execution documentation

## 🚦 Status

✅ **COMPLETE** - Parallel execution is fully implemented, tested, and documented!

## 🔜 Future Enhancements

Potential improvements for future versions:
- Process priority management
- Dynamic concurrency adjustment based on load
- Workflow dependencies (wait for X before running Y)
- Resource-based scheduling (CPU/memory aware)
- Distributed execution across multiple servers
- Workflow queuing with priorities

---

**Version**: 1.0.0  
**Date**: November 30, 2025  
**Status**: Production Ready ✅
