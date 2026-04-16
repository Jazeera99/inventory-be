<?php

namespace Tests;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;

trait TestHttp
{
    /**
     * URL that will be used for json request.
     */
    protected string $url = '';

    protected string $method = '';

    protected array $headers = [];

    /**
     * Set url for json request.
     *
     * @return TestHttp
     *
     * @throws BindingResolutionException
     */
    public function url(string $url, string $method = ''): self
    {
        $this->url = $url;
        $this->method = $method;

        return $this;
    }

    /**
     * Call json request with previously set method and url.
     */
    public function jsonReq(array $data = [], $options = 0): TestResponse
    {
        return $this->json($this->method, $this->url, $data, $this->headers, $options);
    }

    /**
     * Set response GET for controller test index.
     */
    public function jsonGet(string $suffix = ''): TestResponse
    {
        $this->method = 'GET';
        $oldUrl = $this->url;
        $this->url = $this->url."/$suffix";
        $response = $this->jsonReq();
        $this->url = $oldUrl;

        return $response;
    }

    /**
     * Set response POST for controller test store.
     */
    public function jsonPost(array $data = []): TestResponse
    {
        $this->method = 'POST';

        return $this->jsonReq($data);
    }

    /**
     * Set response PUT for controller test update.
     */
    public function jsonPut(array $form): TestResponse
    {
        $this->method = 'PUT';

        return $this->jsonReq($form);
    }

    /**
     * Set response DELETE for controller test destroy.
     */
    public function jsonDelete(array $data = []): TestResponse
    {
        $this->method = 'DELETE';

        return $this->jsonReq($data);
    }

    /**
     * Call GET/POST/PUT/DELETE request and assert the json structure.
     *
     * @param  \Closure(AssertableJson): (AssertableJson)  $callback
     */
    public function assertJsonReq(array $data, \Closure $callback): TestResponse
    {
        return $this->jsonReq($data)
            ->assertSuccessful()
            ->assertJson($callback);
    }

    /**
     * Call GET request and assert the json structure.
     *
     * @param  \Closure(AssertableJson): (AssertableJson)  $callback
     */
    public function assertJsonGet(\Closure $callback): TestResponse
    {
        $this->method = 'GET';

        return $this->assertJsonReq([], $callback);
    }

    /**
     * Call POST request and assert the json structure.
     *
     * @param  array<string,mixed>  $data
     * @param  \Closure(AssertableJson): (AssertableJson)  $callback
     */
    public function assertJsonPost(array $data, \Closure $callback): TestResponse
    {
        $this->method = 'POST';

        return $this->assertJsonReq($data, $callback);
    }

    /**
     * Call PUT request and assert the json structure.
     *
     * @param  array<string,mixed>  $data
     * @param  \Closure(AssertableJson): (AssertableJson)  $callback
     */
    public function assertJsonPut(array $data, \Closure $callback): TestResponse
    {
        $this->method = 'PUT';

        return $this->assertJsonReq($data, $callback);
    }

    /**
     * Call PATCH request and assert the json structure.
     *
     * @param  array<string,mixed>  $data
     * @param  \Closure(AssertableJson): (AssertableJson)  $callback
     */
    public function assertJsonPatch(array $data, \Closure $callback): TestResponse
    {
        $this->method = 'PATCH';

        return $this->assertJsonReq($data, $callback);
    }

    /**
     * Set response PATCH for controller test toggle status or partial update.
     */
    public function jsonPatch(array $data = []): TestResponse
    {
        $this->method = 'PATCH';

        return $this->jsonReq($data);
    }

    /**
     * Call DELETE request and assert the json structure.
     *
     * @param  \Closure(AssertableJson): (AssertableJson)  $callback
     */
    public function assertJsonDelete(array|\Closure $data, ?\Closure $callback = null): TestResponse
    {
        $this->method = 'DELETE';

        if ($data instanceof \Closure) {
            $callback = $data;
            $data = [];
        }

        throw_if($callback === null, new \InvalidArgumentException('Callback is required when data is provided.'));

        return $this->assertJsonReq($data, $callback);
    }

    /**
     * @return array<string,mixed>
     */
    private function prepareJsonErrors(array $errors): array
    {
        $jsonErrors = [];
        foreach ($errors as $key => $error) {
            $langKey = "validation.attributes.{$key}";
            // enable this else if to bypass word like password, status, username that is the same on Indonesian and English
            // if (Lang::has($langKey)) {
            $attribute = Lang::get($langKey);
            $message = str_replace(
                search: [':attribute', ':Attribute'],
                replace: [$attribute, ucfirst($attribute)],
                subject: $error
            );
            // } else {
            //     $unSnakeCase = str_replace('_', ' ', $key);
            //     $message = str_replace(
            //         search: [':attribute', ':Attribute'],
            //         replace: [$unSnakeCase, ucfirst($unSnakeCase)],
            //         subject: $error
            //     );
            // }
            $jsonErrors[$key] = $message;

            $this->assertStringNotContainsString('validation.', $message);
        }

        return $jsonErrors;
    }

    /**
     * Call GET/POST/PUT/DELETE request and assert the json response has error message.
     */
    public function assertJsonReqErrors(array $data, array $errors): TestResponse
    {
        return $this->jsonReq($data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($this->prepareJsonErrors($errors));
    }

    /**
     * Call POST request and assert the json response has error message.
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $errors
     */
    public function assertJsonPostErrors(array $data, array $errors)
    {
        $this->method = 'POST';

        return $this->assertJsonReqErrors($data, $errors);
    }

    /**
     * Call PUT request and assert the json response has error message.
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $errors
     */
    public function assertJsonPutErrors(array $data, array $errors): TestResponse
    {
        $this->method = 'PUT';

        return $this->assertJsonReqErrors($data, $errors);
    }

    /**
     * Call DELETE request and assert the json response has error message.
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $errors
     */
    public function assertJsonDeleteErrors(array $data, array $errors): TestResponse
    {
        $this->method = 'DELETE';

        return $this->assertJsonReqErrors($data, $errors);
    }

    /**
     * Set headers for json request and call the closure, then reset the headers.
     */
    public function requestHeader(array $headers, \Closure $closure): static
    {
        $this->headers = $headers;

        $closure();

        $this->headers = [];

        return $this;
    }
}
