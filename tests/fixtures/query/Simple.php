<?php

namespace UniMapper\Tests\Fixtures\Query;

class Simple extends \UniMapper\Query
{

    public function onExecute(\UniMapper\Mapper $mapper)
    {
        throw new \Exception("You should  mock here!");
    }

}