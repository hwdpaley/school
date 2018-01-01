<?php
/**
 * Created by PhpStorm.
 * User: xuxiaodao
 * Date: 2017/11/28
 * Time: 下午3:46
 */

namespace App\Http\Wechat;


use App\Exceptions\ApiException;
use App\Follow;
use App\Http\Controllers\Controller;
use App\Http\Logic\MatchLoveLogic;
use App\Http\Logic\PaginateLogic;
use App\MatchLove;
use App\User;

class MatchLoveController extends Controller
{
    /**
     * 新增匹配不能为空
     *
     * @author yezi
     *
     * @return mixed
     * @throws ApiException
     */
    public function save()
    {
        $user = request()->input('user');
        $username = request()->input('username');
        $matchName = request()->input('match_name');
        $content = request()->input('content');
        $private = request()->input('privation');

        $rule = [
            'username' => 'required',
            'match_name' => 'required',
            'privation' => 'required'
        ];

        $messages = [
            'username.required'=>'你的名字不能为空',
            'gender.required'=>'匹配的名字不能为空',
            'privation.required'=>'是否匿名不能为空不能为空',
        ];

        $validator = \Validator::make(request()->input(), $rule,$messages);
        if ($validator->fails()) {
            $messages = $validator->errors();
            throw new ApiException($messages->first(), 60001);
        }

        $matchLove = new MatchLoveLogic();
        $result = $matchLove->createMatchLove($user->id,$username,$matchName,$content,$private,$user->{User::FIELD_ID_COLLEGE});

        return $result;
    }

    /**
     * 获取
     *
     * @author yezi
     *
     * @return mixed
     */
    public function matchLoves()
    {
        $user = request()->input('user');
        $pageSize = request()->input('page_size',10);
        $pageNumber = request()->input('page_number',1);
        $type = request()->input('type');
        $orderBy = request()->input('order_by','created_at');
        $sortBy = request()->input('sort_by','desc');

        $pageParams = ['page_size'=>$pageSize, 'page_number'=>$pageNumber];

        $query = MatchLove::query()->with(['user'])
            ->when($type,function ($query)use($user,$type){
                if($type == 2){
                    $query->whereHas('follows',function ($query)use($user,$type){
                        $query->where(Follow::FIELD_ID_USER,$user->id)->where(Follow::FIELD_STATUS,Follow::ENUM_STATUS_FOLLOW);
                    });
                }

                return $query;
            })
            ->orderBy($orderBy,$sortBy);
        if($user->{User::FIELD_ID_COLLEGE}){
            $query->where(MatchLove::FIELD_ID_COLLEGE,$user->{User::FIELD_ID_COLLEGE});
        }

        $saleFriends = app(PaginateLogic::class)->paginate($query,$pageParams, '*',function($saleFriend)use($user){
            return app(MatchLoveLogic::class)->formatSingle($saleFriend,$user);
        });

        return $saleFriends;
    }

    public function newList()
    {
        $user = request()->input('user');
        $time = request()->input('date_time');

        if(empty($time)){
            throw new ApiException('参数错误',60001);
        }

        $query = MatchLove::query()->with(['user'])
            ->where(MatchLove::FIELD_CREATED_AT,'>=',$time)
            ->orderBy('created_at','desc');
        if($user->{User::FIELD_ID_COLLEGE}){
            $query->where(MatchLove::FIELD_ID_COLLEGE,$user->{User::FIELD_ID_COLLEGE});
        }

        $result = $query->get();

        $formatResult = collect($result)->map(function ($item)use($user){
            app(MatchLoveLogic::class)->formatSingle($item,$user);
            return $item;
        });

        return $formatResult;
    }

    /**
     * 详情
     *
     * @author yezi
     *
     * @param $id
     * @return mixed
     */
    public function detail($id)
    {
        $user = request()->input('user');

        $matchLove = MatchLove::with(['user'])->find($id);

        $matchLoveLogic = new MatchLoveLogic();

        return $matchLoveLogic->formatSingle($matchLove,$user);
    }

    /**
     * 删除
     *
     * @author yezi
     *
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        $user = request()->input('user');

        $result = MatchLove::where(MatchLove::FIELD_ID,$id)->where(MatchLove::FIELD_ID_OWNER,$user->id)->delete();

        return $result;
    }

}