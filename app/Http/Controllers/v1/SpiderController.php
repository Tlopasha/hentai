<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Modules\Spider\Base\GetResourceService;
use App\Http\Modules\Spider\BilBiliResourceSpider;
use Illuminate\Http\Request;

class SpiderController extends Controller
{
    public function getUsers(Request $request)
    {
        $user = $request->user();
        if (!$user->is_admin)
        {
            return $this->resErrRole();
        }

        $getResourceService = new GetResourceService();
        $users = $getResourceService->getAllUser();

        return $this->resOK([
            'user' => $users,
            'site' => [
                [
                    'id' => 1,
                    'name' => 'bilibili',
                    'path' => 'https://space.bilibili.com/{id}'
                ]
            ]
        ]);
    }

    public function setUser(Request $request)
    {
        $user = $request->user();
        if ($user->cant('change_spider_user'))
        {
            return $this->resErrRole();
        }

        $userId = $request->get('user_id');
        $site = $request->get('site');
        $rule = $request->get('rule');

        $getResourceService = new GetResourceService($site);
        $user = $getResourceService->setUser($userId, $rule);

        return $this->resOK($user);
    }

    public function delUser(Request $request)
    {
        $user = $request->user();
        if ($user->cant('change_spider_user'))
        {
            return $this->resErrRole();
        }

        $userId = $request->get('user_id');
        $withData = $request->get('with_data');
        $site = $request->get('site');

        $getResourceService = new GetResourceService($site);
        $result = $getResourceService->delUser($userId, $withData);

        return $this->resOK($result);
    }

    public function refreshUserData(Request $request)
    {
        $user = $request->user();
        if ($user->cant('change_spider_user'))
        {
            return $this->resErrRole();
        }

        $userId = $request->get('user_id');
        $site = $request->get('site');

        $resourceSpider = null;
        if ($site === 1)
        {
            $resourceSpider = new BilBiliResourceSpider();
        }

        if ($resourceSpider === null)
        {
            return $this->resErrBad();
        }

        $resourceSpider->updateOldResources(true, $userId);

        return $this->resNoContent();
    }
}