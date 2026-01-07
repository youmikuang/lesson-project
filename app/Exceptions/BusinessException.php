<?php

namespace App\Exceptions;

use Exception;

/**
 * 业务异常基类
 *
 * 用于处理业务逻辑中的可预期异常，提供统一的错误响应格式
 */
class BusinessException extends Exception
{
    protected int $httpStatusCode;

    protected array $data;

    public function __construct(
        string $msg = '业务处理失败',
        int $httpStatusCode = 400,
        array $data = [],
        ?Exception $previous = null
    ) {
        parent::__construct($msg, 0, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->data = $data;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 将异常转换为 HTTP 响应数组
     */
    public function toArray(): array
    {
        $response = [
            'ret' => -1,
            'data' => [],
            'msg' => $this->getMessage(),
        ];

        if (! empty($this->data)) {
            $response['data'] = $this->data;
        }

        return $response;
    }
}
