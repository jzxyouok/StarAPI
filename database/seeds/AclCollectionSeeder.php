<?php

use Illuminate\Database\Seeder;

class AclCollectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // delete records from acl collection
        DB::collection('acl')->delete();

        // insert records into acl collection
        DB::collection('acl')->insert([
            'name'   => 'standard',
            'allows' => [
                'GET' => [
                    "api/v1/{resource}",
                    "api/v1/{resource}/{id}",
                    "api/v1/configuration",
                    "api/v1/profiles",
                    "api/v1/profiles/{profiles}",
                    "api/v1/slack/users"
                ],
                'PUT' => [
                    'api/v1/profiles/changePassword',
                    'api/v1/profiles/{profiles}'
                ],
                'PATCH' => [
                    'api/v1/profiles/{profiles}'
                ]
            ]
            ]);

        $this->command->info("acl collection seeded!");
    }
}
