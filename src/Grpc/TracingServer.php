<?php

namespace MixPlus\Tracing\Grpc;

use Mix\Micro\Register\Helper\ServiceHelper;
use Mix\Grpc\Context;
use MixPlus\Tracing\Zipkin\Zipkin;
use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\ERROR;
use const OpenTracing\Tags\HTTP_METHOD;
use const OpenTracing\Tags\HTTP_STATUS_CODE;
use const OpenTracing\Tags\HTTP_URL;
use const Zipkin\Kind\SERVER;

class TracingServer
{

    public function process(Context $ctx)
    {
        $server = $ctx->request->server;
        $path = $server['request_uri'];
        $slice         = array_filter(explode('/', $path));
        $tmp           = array_filter(explode('.', array_shift($slice)));
        $serviceMethod = sprintf('%s.%s', array_pop($tmp), array_pop($slice));
        $serviceName   = implode('.', $tmp);

        $ip = $server['remote_addr'];
        $port = $server['remote_port'];

        $tracer = (new Zipkin())->startTracer($serviceName, $ip, $port);

        $headers = $ctx->getHeaders();
        $spanContext   = $tracer->extract(TEXT_MAP, $headers);
        $span = $tracer->startSpan($serviceName, [
            'child_of' => $spanContext,
            'tags' => [
                'service.name' => $serviceName,
                'service.method' => $serviceMethod,
                'service.address' => "{$ip}:{$port}",
                HTTP_METHOD => $ctx->request->getMethod(),
                HTTP_URL => $server['request_uri'],
            ],
        ]);

        $traceHeaders = [];
        $tracer->inject($span->getContext(), TEXT_MAP, $traceHeaders);
        $traceID = $traceHeaders['x-b3-traceid'] ?? null;
        if ($traceID) {
            $ctx->response->setHeader('x-b3-traceid', $traceID);
        }

        // 记录 x- 开头的内部 Header 信息
        foreach ($headers as $key => $value) {
            if (stripos($key, 'x-') === 0 && stripos($key, 'x-b3') === false) {
                $span->setTag(sprintf('http.header.%s', $key), $value);
            }
        }

        try {

        } catch (\Throwable $ex) {
            throw $ex;
        } finally {
            // 记录响应信息
            $span->setTag(HTTP_STATUS_CODE, 200);

            $span->finish();
            $tracer->flush();
        }
    }
}