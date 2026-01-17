<?php

namespace App\Services;

use App\Model\Tag;
use Illuminate\Support\Collection;

class TagService
{
    /**
     * 創建新標籤
     *
     * @param string $name
     * @return Tag
     */
    public function create(string $name): Tag
    {
        return Tag::create([
            'name' => $name
        ]);
    }

    /**
     * 更新標籤
     *
     * @param Tag $tag
     * @param string $name
     * @return bool
     */
    public function update(Tag $tag, string $name): bool
    {
        return $tag->update([
            'name' => $name
        ]);
    }

    /**
     * 刪除標籤
     *
     * @param Tag $tag
     * @return bool|null
     */
    public function delete(Tag $tag): ?bool
    {
        return $tag->delete();
    }

    /**
     * 獲取所有標籤
     *
     * @return Collection
     */
    public function getAllTags(): Collection
    {
        return Tag::all();
    }
}
