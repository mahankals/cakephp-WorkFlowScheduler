<?php
declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * Workflows seed.
 */
class WorkflowsSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/migrations/4/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'name' => 'InvoiceEnforcement',
                'description' => 'Fetches weather data and stores it.',
                'schedule' => '* * * * *', // Every minute
                'status' => 1,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ];

        $table = $this->table('workflows');
        $table->insert($data)->save();
    }
}
