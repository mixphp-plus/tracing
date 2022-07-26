<?php

namespace MixPlus\Tracing\Grpc;

use Mix\Vega\Context;
use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\ERROR;

class TracingClient
{
    protected $tracer;

    public function __construct($tracer)
    {
        $this->tracer = $tracer;
    }

    /**
     * @param Context $ctx
     * @throws \Throwable
     */
    public function process(&$ctx)
    {
        $slice = array_filter(explode('/', $ctx->uri()->getPath()));
        $tmp = array_filter(explode('.', array_shift($slice)));
        $serviceMethod = sprintf('%s.%s', array_pop($tmp), array_pop($slice));
        $serviceName = implode('.', $tmp);

        $tracer = $this->tracer;
        $operationName = sprintf('%s:%s', 'grpc.client', $serviceName);
        $scope = $tracer->startActiveSpan($operationName, [
            'tags' => [
                'service.name' => $serviceName,
                'service.method' => $serviceMethod,
                'service.address' => $ctx->header('host') ?? '',
            ],
        ]);

        $traceHeaders = [];
        $tracer->inject($scope->getSpan()->getContext(), TEXT_MAP, $traceHeaders);


        try {

        } catch (\Throwable $ex) {
            $message = sprintf('%s %s in %s on line %s', $ex->getMessage(), get_class($ex), $ex->getFile(), $ex->getLine());
            $code = $ex->getCode();
            $error = sprintf('[%d] %s', $code, $message);
            throw $ex;
        } finally {
            if (isset($error)) {
                $scope->getSpan()->setTag(ERROR, $error);
            }
            $scope->close();
        }
        return $traceHeaders;
    }
}