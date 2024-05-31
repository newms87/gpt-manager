<?php

namespace App\Traits;

use App\Models\Shared\ObjectTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin Model
 */
trait HasObjectTags
{
    public function objectTags(): ObjectTag|MorphToMany
    {
        return $this->morphToMany(ObjectTag::class, 'taggable', 'object_tag_taggables');
    }

    public function createObjectTag($category, $tagName): ObjectTag
    {
        return ObjectTag::firstOrCreate([
            'category' => $category,
            'name'     => $tagName,
        ]);
    }

    public function deleteObjectTag($category, $tagName): bool
    {
        return ObjectTag::where('category', $category)->where('name', $tagName)->first()?->delete();
    }

    public function addObjectTag($category, $tagName): static
    {
        $tag = $this->createObjectTag($category, $tagName);
        $this->objectTags()->syncWithoutDetaching($tag);

        return $this;
    }

    public function setObjectTags($category, $tagNames): static
    {
        $objectTagIds = [];
        foreach($tagNames as $tagName) {
            $objectTagIds[] = $this->createObjectTag($category, $tagName)->id;
        }

        $this->objectTags()->sync($objectTagIds);

        return $this;
    }

    public function removeObjectTag($category, $tagName): static
    {
        $tag = ObjectTag::where('category', $category)->where('name', $tagName)->first();
        $this->objectTags()->detach($tag);

        return $this;
    }
}
