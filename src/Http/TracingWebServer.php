<?php

namespace MixPlus\Tracing\Http;

use Mix\Micro\Register\Helper\ServiceHelper;
use Mix\Vega\Context;
use MixPlus\Tracing\Zipkin\Zipkin;
use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\HTTP_METHOD;
use const OpenTracing\Tags\HTTP_STATUS_CODE;
use const OpenTracing\Tags\HTTP_URL;

class TracingWebServer
{
    protected static $tracer;


    public static function middleware(): \Closure
    {
        return function (Context $ctx) {
            $ctx->next();
            try {
                self::process($ctx);
            } catch (\Throwable $e) {
                $ctx->abortWithStatus(403);
            }
        };
    }

    public static function tracer($serviceName)
    {
        return (new Zipkin())->startTracer($serviceName, ServiceHelper::localIP());
    }

    public static function process(Context $ctx)
    {
        $serviceName = $ctx->request->getUri()->getPath();
        $tracer = self::tracer($serviceName);

        $headers = $ctx->request->getHeadersLine();
        $spanContext = $tracer->extract(TEXT_MAP, $headers);
        $operationName = $ctx->request->getUri()->getPath();
        $span = $tracer->startSpan($operationName, [
            'child_of' => $spanContext,
            'tags' => [
                HTTP_METHOD => $ctx->request->getMethod(),
                HTTP_URL => $ctx->request->getUri()->__toString(),
            ],
        ]);

        // 把 TraceID 发送至用户的 Header 中
        $traceHeaders = [];
        $tracer->inject($span->getContext(), TEXT_MAP, $traceHeaders);
        $traceID = $traceHeaders['x-b3-traceid'] ?? null;
        if ($traceID) {
            $ctx->response->withHeader('x-b3-traceid', $traceID);
        }

        // 记录 x- 开头的内部 Header 信息
        foreach ($ctx->request->getHeadersLine() as $key => $value) {
            if (stripos($key, 'x-') === 0 && stripos($key, 'x-b3') === false) {
                $span->setTag(sprintf('http.header.%s', $key), $value);
            }
        }

        // Tracing::extract

        $ctx->set('__tracer__', $tracer);
        $span->setTag(HTTP_STATUS_CODE, $ctx->response->getStatusCode());
        $span->finish();
        $tracer->flush();
    }
}