<?php


namespace App\Http\Modules\Spider\Base;


use App\Services\Qiniu\Qshell;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use QL\QueryList;
use App\Services\Qiniu\Http\Client;

class GetResourceService
{
    protected $userTable = 'spider_users';
    protected $dataTable = 'spider_resource';
    protected $repeatIds = [];
    /**
     * 资源网站的类型
     * 1. bilibili
     */
    /**
     * source_type
     * 数据列的类型，根据 user 的 rule 来填制，默认为 0
     * 0. 不在某个分类里
     */
    protected $siteType;

    public function __construct($siteType)
    {
        $this->siteType = $siteType;
    }

    /**
     * 自动抓取和更新全部数据
     */
    public function autoload()
    {
        $this->updateOldResources();
        // $this->getNewestResources();
        $this->clearRepeatData();
    }

    public function setUser($id, $rule = '')
    {
        if (!$id)
        {
            return false;
        }

        $hasUser = DB
            ::table($this->userTable)
            ->where('site_type', $this->siteType)
            ->where('user_id', $id)
            ->count();

        $now = Carbon::now();
        if ($hasUser)
        {
            DB
                ::table($this->userTable)
                ->where('site_type', $this->siteType)
                ->where('user_id', $id)
                ->update([
                    'rule' => json_encode($rule),
                    'updated_at' => $now,
                    'deleted_at' => null
                ]);

            DB
                ::table($this->dataTable)
                ->where('site_type', $this->siteType)
                ->where('author_id', $id)
                ->update([
                    'deleted_at' => null
                ]);
        }
        else
        {
            DB
                ::table($this->userTable)
                ->insert([
                    'site_type' => $this->siteType,
                    'user_id' => $id,
                    'rule' => json_encode($rule),
                    'updated_at' => $now,
                    'created_at' => $now
                ]);
        }

        return true;
    }

    public function delUser($id, $withData = false)
    {
        if (!$id)
        {
            return false;
        }

        DB
            ::table($this->userTable)
            ->where('site_type', $this->siteType)
            ->where('user_id', $id)
            ->update([
                'deleted_at' => null
            ]);

        $now = Carbon::now();
        if ($withData)
        {
            DB
                ::table($this->dataTable)
                ->where('site_type', $this->siteType)
                ->where('author_id', $id)
                ->update([
                    'deleted_at' => $now
                ]);
        }

        return true;
    }

    /**
     * TODO：当数据量过大的时候，这个 for 循环很多很多
     * 根据稿件id更新老的存量数据
     */
    public function updateOldResources($forceRefresh = false)
    {
        $list = DB
            ::table($this->dataTable)
            ->where('site_type', $this->siteType)
            ->whereNull('deleted_at')
            ->select(['id', 'model_id', 'model_type', 'data'])
            ->get()
            ->toArray();

        if (empty($list))
        {
            return true;
        }

        $now = Carbon::now();
        foreach ($list as $row)
        {
            try
            {
                $data = $this->getDataItem(
                    $row->model_id,
                    $row->model_type,
                    $forceRefresh ? null : json_decode($row->data, true)
                );

                DB
                    ::table($this->dataTable)
                    ->where('id', $row->id)
                    ->update([
                        'data' => json_encode($data),
                        'updated_at' => $now
                    ]);
            }
            catch (\Exception $e)
            {
                // do nothing
            }
        }

        return true;
    }

    /**
     * 根据用户id获取最新的数据源
     */
    public function getNewestResources()
    {
        $users = DB
            ::table($this->userTable)
            ->where('site_type', $this->siteType)
            ->whereNull('deleted_at')
            ->pluck('rule', 'user_id')
            ->toArray();

        if (empty($users))
        {
            return true;
        }

        foreach ($users as $userId => $rule)
        {
            try
            {
                $list = $this->getUserList($userId, json_decode($rule, true));
            }
            catch (\Exception $e)
            {
                $list = [];
            }

            DB::table($this->dataTable)->insert($list);
        }

        return true;
    }

    public function getDataItem($id, $type, $data)
    {
        return '';
    }

    public function getUserList($id, $rule)
    {
        return [];
    }

    public function getSpider($url)
    {
        return QueryList::get($url, [], [
            'timeout' => 10
        ]);
    }

    public function getClient($url)
    {
        $client = new Client();
        $resp = $client->get($url);
        $body = json_decode($resp->body, true);

        return $body;
    }

    public function getImage($url)
    {
        $qshell = new Qshell();

        return patchImage($qshell->fetch($url));
    }

    public function clearRepeatData()
    {
        $ids = $this->getRepeatIds();

        if (empty($ids))
        {
            return true;
        }

        $this->repeatIds = $ids;

        while (!empty($this->repeatIds))
        {
            DB
                ::table($this->dataTable)
                ->whereIn('id', $this->repeatIds)
                ->delete();

            $this->repeatIds = $this->getRepeatIds();
        }

        return true;
    }

    protected function getRepeatIds()
    {
        $ids = DB
            ::table($this->dataTable)
            ->select(DB::raw('MIN(id) AS id'))
            ->groupBy(['site_type', 'model_type', 'model_id'])
            ->havingRaw('COUNT(id) > 1')
            ->pluck('id')
            ->toArray();

        return $ids;
    }
}