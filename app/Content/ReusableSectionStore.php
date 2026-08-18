<?php

namespace App\Content;

use App\Models\ReusableSection;

class ReusableSectionStore
{
    public function all(): array
    {
        return ReusableSection::orderByDesc('id')->get()->map->toLibraryRow()->all();
    }

    public function save(string $name, array $block, string $by): array
    {
        return ReusableSection::create([
            'name' => $name,
            'type' => $block['type'],
            'block' => $block,
            'created_by' => $by,
        ])->toLibraryRow();
    }

    public function block(int $id): ?array
    {
        return ReusableSection::find($id)?->block;
    }

    public function delete(int $id): bool
    {
        return (bool) ReusableSection::destroy($id);
    }
}
