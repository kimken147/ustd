<?php

namespace Tests\Unit\Utils;

use App\Models\Channel;
use App\Utils\TransactionNoteUtil;
use Tests\TestCase;

class TransactionNoteUtilTest extends TestCase
{
    private TransactionNoteUtil $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = new TransactionNoteUtil();
    }

    private function makeChannel(int $noteType): object
    {
        return (object) ['note_type' => $noteType];
    }

    // ---------------------------------------------------------------
    // Groceries — tier selection by amount
    // ---------------------------------------------------------------

    public function test_groceries_low_amount_returns_valid_note(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_GROCERIES);
        $result = $this->util->randomNote(100, $channel);

        $expected = [
            '手机配件', '甜虾', '冬季新品', '圆领T恤', '印花卫衣',
            '阔腿裤女', '休闲裤女', '蒙奴莎女', '高腰裤女', '小脚裤女',
            '显高裤女', '显瘦裤女', '加绒裤男', '纯黑牛仔裤', '烟灰高腰裤',
            '薄款哈伦裤', '字母卫衣', '宽松卫衣', '复古连衣裙', '秋季毛衣',
            '运动T恤', '运动卫衣', '运动外套', '背心Bra', '运动背心',
            '短款外套', '开襟卫衣', '雪纺睡衣', '甜美网纱睡衣', '迪士尼家居服',
        ];

        $this->assertContains($result, $expected);
    }

    public function test_groceries_medium_amount_returns_valid_note(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_GROCERIES);
        $result = $this->util->randomNote(5000, $channel);

        $expected = [
            '面膜组合', '纯金串珠', '铁艺饰品', '蜜蜡饰品', '婴儿床组',
            '定制手镯', '定制展具', '手工手串', '玩具滑梯', '宝宝滑梯',
        ];

        $this->assertContains($result, $expected);
    }

    public function test_groceries_high_amount_returns_valid_note(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_GROCERIES);
        $result = $this->util->randomNote(15000, $channel);

        $expected = [
            '香奈儿', '古驰', '普拉达', '肌肤之钥', '美妆组合',
            '凡赛司', '茅台酒', '劍南春', '郎酒', '瀘州老窖',
        ];

        $this->assertContains($result, $expected);
    }

    public function test_groceries_very_high_amount_returns_valid_note(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_GROCERIES);
        $result = $this->util->randomNote(25000, $channel);

        $expected = [
            '爱马仕', '茅台酒', '美妆货款', '茅台酒货款', '批发款项',
            '汽车零件', '沙发组', '水晶吊灯', '原木家具', '装修款项',
        ];

        $this->assertContains($result, $expected);
    }

    // ---------------------------------------------------------------
    // Treasure — suffix verification
    // ---------------------------------------------------------------

    public function test_treasure_low_amount_returns_note_with_suffix(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_TREASURE);
        $result = $this->util->randomNote(5000, $channel);

        $this->assertStringEndsWith('虚宝', $result);
        $this->assertNotEquals('虚宝', $result, 'Note should have a name prefix before the suffix');
    }

    public function test_treasure_medium_amount_returns_note_with_suffix(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_TREASURE);
        $result = $this->util->randomNote(20000, $channel);

        $this->assertStringEndsWith('虚宝', $result);
        $this->assertNotEquals('虚宝', $result);
    }

    public function test_treasure_high_amount_returns_note_with_suffix(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_TREASURE);
        $result = $this->util->randomNote(60000, $channel);

        $this->assertStringEndsWith('虚宝', $result);
        $this->assertNotEquals('虚宝', $result);
    }

    // ---------------------------------------------------------------
    // Unknown note type
    // ---------------------------------------------------------------

    public function test_unknown_note_type_returns_empty_string(): void
    {
        $channel = $this->makeChannel(99);
        $result = $this->util->randomNote(5000, $channel);

        $this->assertSame('', $result);
    }

    // ---------------------------------------------------------------
    // Boundary conditions
    // ---------------------------------------------------------------

    public function test_groceries_boundary_2001(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_GROCERIES);
        $result = $this->util->randomNote(2001, $channel);

        // amount=2001 is NOT < 2001, so it falls into the 2001 tier
        $tier2001 = [
            '面膜组合', '纯金串珠', '铁艺饰品', '蜜蜡饰品', '婴儿床组',
            '定制手镯', '定制展具', '手工手串', '玩具滑梯', '宝宝滑梯',
        ];

        $this->assertContains($result, $tier2001);
    }

    public function test_groceries_boundary_2000(): void
    {
        $channel = $this->makeChannel(Channel::NOTE_GROCERIES);
        $result = $this->util->randomNote(2000, $channel);

        // amount=2000 IS < 2001, so it falls into the 200 tier
        $tier200 = [
            '手机配件', '甜虾', '冬季新品', '圆领T恤', '印花卫衣',
            '阔腿裤女', '休闲裤女', '蒙奴莎女', '高腰裤女', '小脚裤女',
            '显高裤女', '显瘦裤女', '加绒裤男', '纯黑牛仔裤', '烟灰高腰裤',
            '薄款哈伦裤', '字母卫衣', '宽松卫衣', '复古连衣裙', '秋季毛衣',
            '运动T恤', '运动卫衣', '运动外套', '背心Bra', '运动背心',
            '短款外套', '开襟卫衣', '雪纺睡衣', '甜美网纱睡衣', '迪士尼家居服',
        ];

        $this->assertContains($result, $tier200);
    }
}
