<?php

namespace Jetea\Framework\Foundation;

/**
 * 只处理 content 实体，方便中间件中传递对象而不是具体的 响应内容。
 *
 * 返回内容 如果 需要特殊处理，则包装为 对象实现 __toString 方法即可
 * 如 json 类型的 response 写一个 实现了  __toString 的类，里边json_encode 同时输出 header 为json 即可
 *
 * !!! 如果返回的是响应是流的话直接 content 为null就行了，
 *
 * Class Response
 *
 * 参考 symfony 实现
 * @see https://github.com/symfony/http-foundation/blob/master/Response.php#L202
 */
class Response
{
    /**
     * @var mixed
     */
    protected $content;

    /**
     * Response constructor.
     * @param mixed $content
     * @throws \Exception
     */
    public function __construct($content = '')
    {
        $this->setContent($content);
    }

    /**
     * Sets the response content.
     *
     * Valid types are strings, numbers, null, and objects that implement a __toString() method.
     *
     * @param mixed $content Content that can be cast to string
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setContent($content)
    {
        if (null !== $content && !is_string($content) && !is_numeric($content) &&
            ! is_callable(array($content, '__toString'))) {
            throw new \Exception(sprintf(
                'The Response content must be a string or object implementing __toString(), "%s" given.',
                gettype($content)
            ));
        }

        $this->content = $content;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    /**
     * Sends HTTP headers and content.
     *
     * @return $this
     */
    public function send()
    {
        echo (string) $this->content;

        return $this;
    }

    public function __toString()
    {
        return (string) $this->content;
    }
}
