<?php
return[
    'bootstrap'=> //控制弹出小窗口的尺寸
        [
            'width'=>1200, //宽度
            'height'=>800 //高度
        ],
    'nav'=> //控制加速页面的右侧导航,至多4个
        [
            [
                'desc'=>'最新官网',
                'color'=>'#6777ef',
                'link'=>'/user'
            ],
            [
                'desc'=>'购买套餐',
                'color'=>'#6777ef',
                'link'=>'/user/shop'
            ],
            [
                'desc'=>'邀请返利',
                'color'=>'#ffa426',
                'link'=>'/user/invite'
            ],
        ],
    'levelDesc'=> // 等级数字 语义化 ，为避免json编码index丢失，前面加小写L，即l0就代表0级
        [
            "l0"=>'免费用户',
            "l1"=>'精英会员',
            "l2"=>'豪华会员',
            "l3"=>'至尊会员',
        ],
    'holdConnect'=>true,
    'online'=>[
        'enable'=>true,
        /**
         * 回调方法，展示节点的信息
         */
        'callback'=>function($user){
            $nodes=\App\Models\Node::where('node_class','<=',$user->class)->where('type',1)->get();
            $buildData = [];
            foreach ($nodes as $node){
                $count = $node->getOnlineUserCount();
                if($count<10){
                    $item=[
                        'text'=>'畅通',
                        'color'=>'#52c41a'
                    ];
                }elseif ($count>30){
                    $item=[
                        'text'=>'拥挤',
                        'color'=>'#faad14'
                    ];
                }else{
                    $item=[
                        'text'=>'爆满',
                        'color'=>'#d9363e'
                    ];
                }
                $buildData[$node->name] = $item;
            }
            return $buildData;
        }
    ]
];