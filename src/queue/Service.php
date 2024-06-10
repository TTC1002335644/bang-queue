<?php
/**
 * This file is part of thinkphp.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    Bang<1002335644@qq.com>
 */

declare (strict_types=1);

namespace bang\queue\queue;

use bang\queue\queue\Work;

class Service extends \think\Service
{

    public function register()
    {
    }

    public function boot()
    {
        $this->commands([
            Work::class,
        ]);

    }

}