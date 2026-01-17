<?php

namespace App\Utils;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

trait GuzzleHttpClientTrait
{
    public function makeClient(): Client
    {
        $stack = HandlerStack::create();
        $stack->push($this->logMiddleware());

        return app(Client::class, [
            'config' => [
                'handler'                       => $stack,
                RequestOptions::HTTP_ERRORS     => false,
                RequestOptions::TIMEOUT         => 10,
                RequestOptions::CONNECT_TIMEOUT => 10,
                RequestOptions::VERIFY          => false,
            ],
        ]);
    }

    protected function logMiddleware()
    {
        return function (callable $handler) {
            return function (
                RequestInterface $request,
                array $options
            ) use ($handler) {
                $requestLog = $this->stringOfRequest($request);
                $startedAt = Carbon::now();

                $request->getBody()->rewind();

                $promise = $handler($request, $options);

                return $promise->then(
                    function (ResponseInterface $response) use ($requestLog, $startedAt) {
                        Log::debug(
                            $requestLog
                                . PHP_EOL . PHP_EOL
                                . $this->stringOfResponse($response) . PHP_EOL . PHP_EOL
                                . '==== total time in second ====' . PHP_EOL
                                . "Started At: " . $startedAt->toIso8601String() . PHP_EOL
                                . "Ended At: " . ($endedAt = Carbon::now())->toIso8601String() . PHP_EOL
                                . $endedAt->diffInRealSeconds($startedAt)
                        );

                        $response->getBody()->rewind();

                        return $response;
                    },
                    function (RequestException $requestException) use ($requestLog, $startedAt) {
                        Log::debug(
                            $requestLog
                                . PHP_EOL . PHP_EOL
                                . $requestException->getMessage() . PHP_EOL . PHP_EOL
                                . '==== total time in second ====' . PHP_EOL
                                . "Started At: " . $startedAt->toIso8601String() . PHP_EOL
                                . "Ended At: " . ($endedAt = Carbon::now())->toIso8601String() . PHP_EOL
                                . $endedAt->diffInRealSeconds($startedAt)
                        );

                        throw $requestException;
                    }
                );
            };
        };
    }

    /**
     * @param RequestInterface $request
     * @return bool|string
     *
     * @see \Illuminate\Http\Response __toString
     */
    protected function stringOfRequest(RequestInterface $request)
    {
        try {
            $content = $request->getBody()->getContents();
        } catch (LogicException $e) {
            return trigger_error($e, E_USER_ERROR);
        }

        return
            sprintf('%s %s %s', $request->getMethod(), $request->getUri(), $request->getProtocolVersion()) . "\r\n" .
            $this->headers($request) . "\r\n" .
            Str::limit($content, 5000);
    }

    /**
     * @param ResponseInterface $message
     * @return string
     *
     * @see \Illuminate\Http\Response __toString
     */
    protected function stringOfResponse(ResponseInterface $message)
    {
        return
            trim(sprintf(
                'HTTP/%s %s %s',
                $message->getProtocolVersion(),
                $message instanceof ResponseInterface ? $message->getStatusCode() : '',
                $message instanceof ResponseInterface ? data_get(Response::$statusTexts, $message->getStatusCode(), '') : ''
            )) . "\r\n" .
            $this->headers($message) . "\r\n" .
            Str::limit($message->getBody(), 5000);
    }

    /**
     * @param MessageInterface $message
     * @return string
     *
     * @see ResponseHeaderBag __toString()
     */
    private function headers(MessageInterface $message)
    {
        if (!$headers = $message->getHeaders()) {
            return '';
        }

        ksort($headers);
        $max = max(array_map('strlen', array_keys($headers))) + 1;
        $content = '';
        foreach ($headers as $name => $values) {
            $name = ucwords($name, '-');
            foreach ($values as $value) {
                $content .= sprintf("%-{$max}s %s\r\n", $name . ':', $value);
            }
        }

        return $content;
    }
}
