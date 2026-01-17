<?php


namespace App\Utils;


use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Models\Channel;

/**
 * 交易附言專用
 * @package App\Utils
 */
class TransactionNoteUtil
{

    /**
     * @var Collection
     */
    private $groceriesNotes;

    private $treasureNotes;

    private $reNotes;

    public function __construct()
    {
        $this->groceriesNotes = collect([
            200   => [
                '手机配件', '甜虾', '冬季新品', '圆领T恤', '印花卫衣',
                '阔腿裤女', '休闲裤女', '蒙奴莎女', '高腰裤女', '小脚裤女',
                '显高裤女', '显瘦裤女', '加绒裤男', '纯黑牛仔裤', '烟灰高腰裤',
                '薄款哈伦裤', '字母卫衣', '宽松卫衣', '复古连衣裙', '秋季毛衣',
                '运动T恤', '运动卫衣', '运动外套', '背心Bra', '运动背心',
                '短款外套', '开襟卫衣', '雪纺睡衣', '甜美网纱睡衣', '迪士尼家居服',
            ],
            2001  => [
                '面膜组合', '纯金串珠', '铁艺饰品', '蜜蜡饰品', '婴儿床组',
                '定制手镯', '定制展具', '手工手串', '玩具滑梯', '宝宝滑梯',
            ],
            10001 => [
                '香奈儿', '古驰', '普拉达', '肌肤之钥', '美妆组合',
                '凡赛司', '茅台酒', '劍南春', '郎酒', '瀘州老窖',
            ],
            20001 => [
                '爱马仕', '茅台酒', '美妆货款', '茅台酒货款', '批发款项',
                '汽车零件', '沙发组', '水晶吊灯', '原木家具', '装修款项',
            ],
        ]);

        $this->treasureNotes = collect([
            10000   => [
                '艾丽西亚', '克莉丝', '迪莉亚', '伊丽莎', '莫莉',
                '卡洛琳', '乔治娜', '伊莎贝拉', '琳达', '梅兰妮',
                '玛格丽特', '凯瑟琳', '洛娜', '米雪儿', '索菲亚',
                '苏珊娜', '波妮', '派翠西亚', '罗莎莉', '史黛拉',
                '妮塔', '梅丽莎', '维罗妮卡', '蕾妮', '娜塔莉',
                '玛德琳', '莱拉', '露西', '乔伊思', '多萝西',
            ],
            30000  => [
                '亚伯拉罕', '亚当', '阿德里安', '亚历山大', '阿利斯泰尔',
                '安德鲁', '安迪', '安格斯', '安东尼', '奥古斯丁',
                '奥布里', '巴纳比', '巴里', '巴塞洛缪', '伯纳德',
                '伯尼', '凯撒', '比利', '布伦丹', '查理',
                '查克', '克里斯', '克劳德', '克莱尔', '科林',
                '丹尼尔', '克林特', '戴夫', '大卫', '丹尼',
            ],
            50000 => [
                '克莱德', '德莫特', '多米尼克', '唐纳德', '道格拉斯',
                '德怀特', '埃德加', '埃尔罗伊', '艾略特', '弗洛伊德',
                '杰夫', '乔治', '戈弗雷', '戈登', '亨利',
                '赫伯特', '雨果', '杰米', '詹姆士', '杰克',
                '约翰', '吉米', '马丁', '雷克斯', '鲁道夫',
                '罗恩', '肖恩', '特洛伊', '泰德', '西蒙',
            ],
        ]);

        $this->reNotes = collect([
            '生活如意', '事业高升', '前程似锦', '美梦成真', '岁岁今朝', '事事顺利',
            '万事如意', '愿与同僚', '共分此乐', '事业有成', '多财满家', '家肥屋润',
            '彩蝶翩翩', '余钱多多', '生意兴隆', '财源广进', '长命百岁', '福如东海',
            '寿比南山', '寿与天齐', '蒸蒸日上', '日新月异', '财源滚滚', '百年好合',
            '龙马精神', '开门大吉', '意气风发', '好事连连', '花开富贵', '文定吉祥',
            '万事如意', '事事顺心', '福寿安康', '笑口常开', '家庭和睦', '事业有成',
            '幸福快乐', '年年有余', '青春常在', '横财就手', '前程似锦', '财运亨通',
            '飞黄腾达', '一本万利', '货如轮转', '拾己救人', '义行可风', '春晖广被',
            '佳偶天成', '宜室宜家', '白头偕老', '百年琴瑟', '岁岁平安', '大展鸿图',
            '大展经纶', '同业楷模', '美仑美奂', '才华潢溢', '名冠群伦', '前程万里',
            '淑德可风', '教子有方', '德术兼备', '二龙起飞', '三羊开泰', '四季安全',
            '五福临门', '六六大顺'
        ]);
    }

    public function randomNote($amount, $channel): string
    {
        if ($channel->code == Channel::CODE_RE_ALIPAY) {
            return $this->reNotes->random();
        }

        if ($channel->note_type == Channel::NOTE_GROCERIES) {
            if ($amount < 2001) {
                return Arr::random($this->groceriesNotes->get(200));
            } elseif ($amount < 10001) {
                return Arr::random($this->groceriesNotes->get(2001));
            } elseif ($amount < 20001) {
                return Arr::random($this->groceriesNotes->get(10001));
            } else {
                return Arr::random($this->groceriesNotes->get(20001));
            }
        }

        if ($channel->note_type == Channel::NOTE_TREASURE) {
            if ($amount < 10001) {
                return Arr::random($this->treasureNotes->get(10000)) . '虚宝';
            } elseif ($amount < 30001) {
                return Arr::random($this->treasureNotes->get(30000)) . '虚宝';
            } else {
                return Arr::random($this->treasureNotes->get(50000)) . '虚宝';
            }
        }

        return '';
    }
}
