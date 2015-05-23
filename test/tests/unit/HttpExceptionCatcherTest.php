<?php

namespace WellRESTed\HttpExceptions\Test\Unit;

use Prophecy\Argument;
use WellRESTed\HttpExceptions\HttpException;
use WellRESTed\HttpExceptions\HttpExceptionCatcher;

/**
 * @coversDefaultClass WellRESTed\HttpExceptions\HttpExceptionCatcher
 */
class HttpExceptionCatcherTest extends \PHPUnit_Framework_TestCase
{
    private $request;
    private $response;
    private $next;
    private $dispatcher;

    public function setUp()
    {
        parent::setUp();
        $this->request = $this->prophesize('Psr\Http\Message\ServerRequestInterface');
        $this->response = $this->prophesize('Psr\Http\Message\ResponseInterface');
        $this->response->withStatus(Argument::cetera())->willReturn($this->response->reveal());
        $this->response->withHeader(Argument::cetera())->willReturn($this->response->reveal());
        $this->response->withBody(Argument::any())->willReturn($this->response->reveal());
        $this->next = function ($request, $response) {
            return $response;
        };
        $this->dispatcher = $this->prophesize('WellRESTed\Dispatching\DispatcherInterface');
        $this->dispatcher->dispatch(Argument::cetera())->will(function ($args) {
            list($middleware, $request, $response, $next) = $args;
            return $middleware($request, $response, $next);
        });
    }

    /**
     * @covers ::__invoke
     * @covers ::getResponseForHttpException
     */
    public function testHttpExceptionSetsResponseStatusToExceptionCode()
    {
        $code = 404;
        $message = "404 Not Found";

        $catcher = new HttpExceptionCatcher($this->dispatcher->reveal());
        $catcher->add(function ($request, $response, $next) use ($message, $code) {
            throw new HttpException($message, $code);
        });
        $catcher($this->request->reveal(), $this->response->reveal(), $this->next);
        $this->response->withStatus($code)->shouldHaveBeenCalled();
    }

    /**
     * @covers ::__invoke
     * @covers ::getResponseForHttpException
     */
    public function testHttpExceptionSetsResponseBodyToContainExceptionMessage()
    {
        $code = 404;
        $message = "404 Not Found";

        $catcher = new HttpExceptionCatcher($this->dispatcher->reveal());
        $catcher->add(function ($request, $response, $next) use ($message, $code) {
            throw new HttpException($message, $code);
        });
        $catcher($this->request->reveal(), $this->response->reveal(), $this->next);
        $this->response->withBody(Argument::that(function ($body) use ($message) {
            $body = (string) $body;
            return strpos($body, $message) !== false;
        }))->shouldHaveBeenCalled();
    }

    /**
     * @covers ::__invoke
     * @covers ::getResponseForHttpException
     */
    public function testDefaultResponseAddsContentTypeTextHtmlHeader()
    {
        $code = 404;
        $message = "404 Not Found";

        $catcher = new HttpExceptionCatcher($this->dispatcher->reveal());
        $catcher->add(function ($request, $response, $next) use ($message, $code) {
            throw new HttpException($message, $code);
        });
        $catcher($this->request->reveal(), $this->response->reveal(), $this->next);
        $this->response->withHeader("Content-type", "text/html")->shouldHaveBeenCalled();
    }

    /**
     * @covers ::__invoke
     * @covers ::getResponseForException
     * @expectedException \Exception
     */
    public function testDoesNotCatchOtherExceptionsByDefault()
    {
        $catcher = new HttpExceptionCatcher($this->dispatcher->reveal());
        $catcher->add(function ($request, $response, $next) {
            throw new \Exception("Rethrow me!");
        });
        $catcher($this->request->reveal(), $this->response->reveal(), $this->next);
    }
}

