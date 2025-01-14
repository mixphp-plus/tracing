<?php

namespace MixPlus\Tracing;

use Mix\Context\Context;

/**
 * Class Tracing
 * @package MixPlus\Tracing
 */
class Tracing
{

    /**
     * 从上下文提取 Tracer
     * @param Context $context
     * @return \OpenTracing\Tracer
     * @throws \InvalidArgumentException
     */
    public static function extract(Context $context)
    {
        return $context->value('__tracer__');
    }

}
