<?php

use App\Models\Bank;

use Illuminate\Database\Seeder;



class BankSeeder extends Seeder

{
    private $banks = [
        ["name" => "中国银⾏"],
        ["name" => "中国⼯商银⾏"],
        ["name" => "中国建设银⾏"],
        ["name" => "中国农业银⾏"],
        ["name" => "中国邮政储蓄银⾏"],
        ["name" => "中国光⼤银⾏"],
        ["name" => "招商银⾏"],
        ["name" => "交通银⾏"],
        ["name" => "中信银⾏"],
        ["name" => "兴业银⾏"],
        ["name" => "中国⺠⽣银⾏"],
        ["name" => "华夏银⾏"],
        ["name" => "四川天府银⾏"],
        ["name" => "平安银⾏"],
        ["name" => "北京银⾏"],
        ["name" => "上海银⾏"],
        ["name" => "南京银⾏"],
        ["name" => "渤海银⾏"],
        ["name" => "宁波银⾏"],
        ["name" => "上海农村商业银⾏"],
        ["name" => "杭州银⾏"],
        ["name" => "浙商银⾏"],
        ["name" => "徽商银⾏"],
        ["name" => "⼴州银⾏"],
        ["name" => "⻓沙银⾏"],
        ["name" => "青岛银⾏"],
        ["name" => "天津银⾏"],
        ["name" => "恒丰银⾏"],
        ["name" => "成都农村商业银⾏"],
        ["name" => "浙江⺠泰商业银⾏"],
        ["name" => "盛京银⾏"],
        ["name" => "福建海峡银⾏"],
        ["name" => "莱商银⾏"],
        ["name" => "郑州银⾏"],
        ["name" => "上海浦东发展银⾏"],
        ["name" => "厦⻔银⾏"],
        ["name" => "桂林银⾏"],
        ["name" => "⼴⻄北部湾银⾏"],
        ["name" => "浙江省农村信⽤社联合社"],
        ["name" => "南宁江南国⺠村镇银⾏"],
        ["name" => "重庆农村商业银⾏"],
        ["name" => "⼭东省农村信⽤社联合社"],
        ["name" => "柳州银⾏"],
        ["name" => "中原银⾏"],
        ["name" => "乐⼭市商业银⾏"],
        ["name" => "河南省农村信⽤社联合社"],
        ["name" => "中旅银⾏"],
        ["name" => "⼴⻄壮族⾃治区农村信⽤社联合社"],
        ["name" => "福建省农村信⽤社联合社"],
        ["name" => "湖南省农村信⽤社联合社"],
        ["name" => "湖北省农村信⽤社联合社"],
        ["name" => "张家⼝银⾏"],
        ["name" => "晋中银⾏"],
        ["name" => "晋城银⾏"],
        ["name" => "银座银⾏"],
        ["name" => "安徽省农村信⽤社联合社"],
        ["name" => "⼴州农商银⾏"],
        ["name" => "东莞农商银⾏"],
        ["name" => "深圳农商银⾏"],
        ["name" => "顺德农商银⾏"],
        ["name" => "河南伊川农商银⾏"],
        ["name" => "⼴东省农村信⽤社联合社"],
        ["name" => "四川省农村信⽤社联合社"],
        ["name" => "江⻄省农村信⽤社联合社"],
        ["name" => "珠海市农村信⽤社联合社"],
        ["name" => "云南省农村信⽤社联合社"],
        ["name" => "重庆银⾏"],
        ["name" => "贵州省农村信⽤社联合社"],
        ["name" => "珠海农商银⾏"],
        ["name" => "⼴东南粤银⾏"],
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
