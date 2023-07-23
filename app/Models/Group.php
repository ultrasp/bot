<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = ['title', 'code'];

    public function receivers(): BelongsToMany
    {
        return $this->belongsToMany(Receiver::class);
    }

    public static function getbyCode(string $code)
    {
        return self::firstOrNew(['code' => $code]);
    }

    public static function saveItems(array $excelGroups)
    {
        $groups = Group::get();
        $nonRemoveGroupIds = [];
        foreach ($excelGroups as $excelGroup) {

            if (!empty($excelGroup['code'])) {
                $group = self::getbyCode($excelGroup['code']);
                $group->title = $excelGroup['title'];
                $group->isShow = $excelGroup['isShow'];
                $group->save();
                $group->receivers()->sync($excelGroup['users']);
                $nonRemoveGroupIds[] = $group->id;
            }
        }
        // dd($nonRemoveGroupIds);
        foreach ($groups as $group) {
            if(!in_array($group->id,$nonRemoveGroupIds)){
                $group->receivers()->detach();
                $group->delete();
            }
        }

    }
}