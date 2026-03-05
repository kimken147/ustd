<?php

use App\Models\Bank;

use Illuminate\Database\Seeder;



class BankSeeder extends Seeder

{
    private $banks = [
        ['name' => 'TRC-20'],
        ['name' => 'ERC-20'],
        ['name' => 'BEP-20'],
    ];



    /**

     * Run the database seeds.

     *

     * @return void

     */

    public function run()
    {

        foreach ($this->banks as $bank) {

            if (!Bank::where('name', $bank['name'])->exists()) {

                $insertBank = array_merge($bank, [

                    'created_at' => Date('Y-m-d H:i:s'),

                    'updated_at' => Date('Y-m-d H:i:s'),

                ]);

                Bank::insert($insertBank);
            }
        }
    }
}
