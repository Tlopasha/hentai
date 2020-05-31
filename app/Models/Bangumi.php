<?php


namespace App\Models;


use App\Services\Relation\Traits\CanBeLiked;
use App\Services\Relation\Traits\CanBeSubscribed;
use Illuminate\Database\Eloquent\Model;

class Bangumi extends Model
{
    use CanBeLiked, CanBeSubscribed;

    protected $table = 'bangumis';

    protected $fillable = [
        'slug',
        'title',
        'alias',
        'intro',
        'avatar',
        'source_id',
        'parent_slug',
        'is_parent',
        'migration_state',
        'rank',
        'score',
        'like_user_count',      // 答题通过，「加入」的用户
        'subscribe_user_count', // 接受推送，「关注」的用户
        'publish_pin_count',
        'type',                 // 0：番剧，1；游戏，9：其它
        'update_week',          // 0：不更新，1 ~ 7：星期一 ~ 星期日
        'published_at'
    ];

    protected $casts = [
        'is_parent' => 'boolean'
    ];

    public function setAvatarAttribute($url)
    {
        $this->attributes['avatar'] = trimImage($url);
    }

    public function getAvatarAttribute($avatar)
    {
        return patchImage($avatar, 'default-avatar');
    }

    public function tags()
    {
        return $this->belongsToMany(
            'App\Models\BangumiTag',
            'bangumi_tag_relations',
            'bangumi_slug',
            'tag_slug',
            'slug',
            'slug'
        );
    }

    public function serialization()
    {
        return $this->hasOne(BangumiSerialization::class, 'bangumi_id', 'id');
    }
}
