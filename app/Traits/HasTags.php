<?php

namespace App\Traits;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin Model
 */
trait HasTags
{
    public function tags(): MorphToMany|Tag
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables');
    }

    public function attachTag(Tag|string $tag): static
    {
        if (is_string($tag)) {
            $tag = $this->findOrCreateTag($tag);
        }

        $this->tags()->syncWithoutDetaching($tag);

        return $this;
    }

    public function detachTag(Tag|string $tag): static
    {
        if (is_string($tag)) {
            $tag = $this->findTag($tag);
            if (!$tag) {
                return $this;
            }
        }

        $this->tags()->detach($tag);

        return $this;
    }

    public function syncTags(array $tags): static
    {
        $tagIds = [];

        foreach ($tags as $tag) {
            if (is_string($tag)) {
                $tag = $this->findOrCreateTag($tag);
            }

            $tagIds[] = $tag->id;
        }

        $this->tags()->sync($tagIds);

        return $this;
    }

    public function hasTag(Tag|string $tagName): bool
    {
        if ($tagName instanceof Tag) {
            return $this->tags()->where('tags.id', $tagName->id)->exists();
        }

        return $this->tags()->where('tags.name', $tagName)->exists();
    }

    public function scopeWithTag(Builder $query, string $tagName): Builder
    {
        return $query->whereHas('tags', function (Builder $q) use ($tagName) {
            $q->where('name', $tagName);
        });
    }

    public function scopeWithTagType(Builder $query, string $type): Builder
    {
        return $query->whereHas('tags', function (Builder $q) use ($type) {
            $q->where('type', $type);
        });
    }

    protected function findOrCreateTag(string $name, ?string $type = null): Tag
    {
        return Tag::firstOrCreate([
            'team_id' => $this->team_id,
            'name'    => $name,
            'type'    => $type,
        ]);
    }

    protected function findTag(string $name, ?string $type = null): ?Tag
    {
        return Tag::where('team_id', $this->team_id)
            ->where('name', $name)
            ->where('type', $type)
            ->first();
    }
}
