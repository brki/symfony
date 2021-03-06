<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Response;

class ResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testIsValidateable()
    {
        $response = new Response('', 200, array('Last-Modified' => $this->createDateTimeOneHourAgo()->format(DATE_RFC2822)));
        $this->assertTrue($response->isValidateable(), '->isValidateable() returns true if Last-Modified is present');

        $response = new Response('', 200, array('ETag' => '"12345"'));
        $this->assertTrue($response->isValidateable(), '->isValidateable() returns true if ETag is present');

        $response = new Response();
        $this->assertFalse($response->isValidateable(), '->isValidateable() returns false when no validator is present');
    }

    public function testGetDate()
    {
        $response = new Response('', 200, array('Date' => $this->createDateTimeOneHourAgo()->format(DATE_RFC2822)));
        $this->assertEquals(0, $this->createDateTimeOneHourAgo()->diff($response->getDate())->format('%s'), '->getDate() returns the Date header if present');

        $response = new Response();
        $date = $response->getDate();
        $this->assertLessThan(1, $date->diff(new \DateTime(), true)->format('%s'), '->getDate() returns the current Date if no Date header present');

        $response = new Response('', 200, array('Date' => $this->createDateTimeOneHourAgo()->format(DATE_RFC2822)));
        $now = $this->createDateTimeNow();
        $response->headers->set('Date', $now->format(DATE_RFC2822));
        $this->assertEquals(0, $now->diff($response->getDate())->format('%s'), '->getDate() returns the date when the header has been modified');
    }

    public function testGetMaxAge()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 's-maxage=600, max-age=0');
        $this->assertEquals(600, $response->getMaxAge(), '->getMaxAge() uses s-maxage cache control directive when present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=600');
        $this->assertEquals(600, $response->getMaxAge(), '->getMaxAge() falls back to max-age when no s-maxage directive present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Expires', $this->createDateTimeOneHourLater()->format(DATE_RFC2822));
        $this->assertEquals(3600, $response->getMaxAge(), '->getMaxAge() falls back to Expires when no max-age or s-maxage directive present');

        $response = new Response();
        $this->assertNull($response->getMaxAge(), '->getMaxAge() returns null if no freshness information available');
    }

    public function testSetSharedMaxAge()
    {
        $response = new Response();
        $response->setSharedMaxAge(20);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertEquals('public, s-maxage=20', $cacheControl);
    }

    public function testIsPrivate()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100');
        $response->setPrivate();
        $this->assertEquals(100, $response->headers->getCacheControlDirective('max-age'), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertTrue($response->headers->getCacheControlDirective('private'), '->isPrivate() adds the private Cache-Control directive when set to true');

        $response = new Response();
        $response->headers->set('Cache-Control', 'public, max-age=100');
        $response->setPrivate();
        $this->assertEquals(100, $response->headers->getCacheControlDirective('max-age'), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertTrue($response->headers->getCacheControlDirective('private'), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertFalse($response->headers->hasCacheControlDirective('public'), '->isPrivate() removes the public Cache-Control directive');
    }

    public function testExpire()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100');
        $response->expire();
        $this->assertEquals(100, $response->headers->get('Age'), '->expire() sets the Age to max-age when present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100, s-maxage=500');
        $response->expire();
        $this->assertEquals(500, $response->headers->get('Age'), '->expire() sets the Age to s-maxage when both max-age and s-maxage are present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=5, s-maxage=500');
        $response->headers->set('Age', '1000');
        $response->expire();
        $this->assertEquals(1000, $response->headers->get('Age'), '->expire() does nothing when the response is already stale/expired');

        $response = new Response();
        $response->expire();
        $this->assertFalse($response->headers->has('Age'), '->expire() does nothing when the response does not include freshness information');
    }

    public function testGetTtl()
    {
        $response = new Response();
        $this->assertNull($response->getTtl(), '->getTtl() returns null when no Expires or Cache-Control headers are present');

        $response = new Response();
        $response->headers->set('Expires', $this->createDateTimeOneHourLater()->format(DATE_RFC2822));
        $this->assertLessThan(1, 3600 - $response->getTtl(), '->getTtl() uses the Expires header when no max-age is present');

        $response = new Response();
        $response->headers->set('Expires', $this->createDateTimeOneHourAgo()->format(DATE_RFC2822));
        $this->assertLessThan(0, $response->getTtl(), '->getTtl() returns negative values when Expires is in part');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=60');
        $this->assertLessThan(1, 60 - $response->getTtl(), '->getTtl() uses Cache-Control max-age when present');
    }

    public function testSetClientTtl()
    {
        $response = new Response();
        $response->setClientTtl(10);

        $this->assertEquals($response->getMaxAge(), $response->getAge() + 10);
    }

    public function testGetSetProtocolVersion()
    {
        $response = new Response();

        $this->assertEquals('1.0', $response->getProtocolVersion());

        $response->setProtocolVersion('1.1');

        $this->assertEquals('1.1', $response->getProtocolVersion());
    }

    public function testGetVary()
    {
        $response = new Response();
        $this->assertEquals(array(), $response->getVary(), '->getVary() returns an empty array if no Vary header is present');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language');
        $this->assertEquals(array('Accept-Language'), $response->getVary(), '->getVary() parses a single header name value');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language User-Agent    X-Foo');
        $this->assertEquals(array('Accept-Language', 'User-Agent', 'X-Foo'), $response->getVary(), '->getVary() parses multiple header name values separated by spaces');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language,User-Agent,    X-Foo');
        $this->assertEquals(array('Accept-Language', 'User-Agent', 'X-Foo'), $response->getVary(), '->getVary() parses multiple header name values separated by commas');
    }

    public function testSetVary()
    {
        $response = new Response();
        $response->setVary('Accept-Language');
        $this->assertEquals(array('Accept-Language'), $response->getVary());

        $response->setVary('Accept-Language, User-Agent');
        $this->assertEquals(array('Accept-Language', 'User-Agent'), $response->getVary(), '->setVary() replace the vary header by default');

        $response->setVary('X-Foo', false);
        $this->assertEquals(array('Accept-Language', 'User-Agent'), $response->getVary(), '->setVary() doesn\'t change the Vary header if replace is set to false');
    }

    public function testDefaultContentType()
    {
        $headerMock = $this->getMock('Symfony\Component\HttpFoundation\ResponseHeaderBag', array('set'));
        $headerMock->expects($this->at(0))
            ->method('set')
            ->with('Content-Type', 'text/html; charset=UTF-8');
        $headerMock->expects($this->at(1))
            ->method('set')
            ->with('Content-Type', 'text/html; charset=Foo');

        $response = new Response();
        $response->headers = $headerMock;

        // verify first set()
        $response->__toString();

        $response->headers->remove('Content-Type');
        $response->setCharset('Foo');
        // verify second set()
        $response->__toString();
    }

    public function testContentTypeCharset()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');

        // force fixContentType() to be called
        $response->__toString();

        $this->assertEquals('text/css; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testSetCache()
    {
        $response = new Response();
        //array('etag', 'last_modified', 'max_age', 's_maxage', 'private', 'public')
        try {
            $response->setCache(array("wrong option" => "value"));
            $this->fail('->setCache() throws an InvalidArgumentException if an option is not supported');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '->setCache() throws an InvalidArgumentException if an option is not supported');
        }

        $options = array('etag' => '"whatever"');
        $response->setCache($options);
        $this->assertEquals($response->getEtag(), '"whatever"');

        $now = new \DateTime();
        $options = array('last_modified' => $now);
        $response->setCache($options);
        $this->assertEquals($response->getLastModified()->getTimestamp(), $now->getTimestamp());

        $options = array('max_age' => 100);
        $response->setCache($options);
        $this->assertEquals($response->getMaxAge(), 100);

        $options = array('s_maxage' => 200);
        $response->setCache($options);
        $this->assertEquals($response->getMaxAge(), 200);

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));

        $response->setCache(array('public' => true));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));

        $response->setCache(array('public' => false));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));

        $response->setCache(array('private' => true));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));

        $response->setCache(array('private' => false));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
    }

    public function testSendContent()
    {
        $response = new Response('test response rendering', 200);

        ob_start();
        $response->sendContent();
        $string = ob_get_clean();
        $this->assertContains('test response rendering', $string);
    }

    public function testSetPublic()
    {
        $response = new Response();
        $response->setPublic();

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
    }

    public function testSetExpires()
    {
        $response = new Response();
        $response->setExpires(null);

        $this->assertNull($response->getExpires(), '->setExpires() remove the header when passed null');

        $now = new \DateTime();
        $response->setExpires($now);

        $this->assertEquals($response->getExpires()->getTimestamp(), $now->getTimestamp());
    }

    public function testSetLastModified()
    {
        $response = new Response();
        $response->setLastModified(new \DateTime());
        $this->assertNotNull($response->getLastModified());

        $response->setLastModified(null);
        $this->assertNull($response->getLastModified());
    }

    public function testIsInvalid()
    {
        $response = new Response();

        try {
            $response->setStatusCode(99);
            $this->fail();
        } catch(\InvalidArgumentException $e) {
            $this->assertTrue($response->isInvalid());
        }

        try {
            $response->setStatusCode(650);
            $this->fail();
        } catch(\InvalidArgumentException $e) {
            $this->assertTrue($response->isInvalid());
        }

        $response = new Response('', 200);
        $this->assertFalse($response->isInvalid());
    }

    public function testIsInformational()
    {
        $response = new Response('', 100);
        $this->assertTrue($response->isInformational());

        $response = new Response('', 200);
        $this->assertFalse($response->isInformational());
    }

    public function testIsRedirectRedirection()
    {
        foreach (array(301, 302, 303, 307) as $code)
        {
            $response = new Response('', $code);
            $this->assertTrue($response->isRedirection());
            $this->assertTrue($response->isRedirect());
        }

        $response = new Response('', 304);
        $this->assertTrue($response->isRedirection());
        $this->assertFalse($response->isRedirect());

        $response = new Response('', 200);
        $this->assertFalse($response->isRedirection());
        $this->assertFalse($response->isRedirect());

        $response = new Response('', 404);
        $this->assertFalse($response->isRedirection());
        $this->assertFalse($response->isRedirect());

        $response = new Response('', 301, array('Location' => '/good-uri'));
        $this->assertFalse($response->isRedirect('/bad-uri'));
        $this->assertTrue($response->isRedirect('/good-uri'));
    }

    public function testIsNotFound()
    {
        $response = new Response('', 404);
        $this->assertTrue($response->isNotFound());

        $response = new Response('', 200);
        $this->assertFalse($response->isNotFound());
    }

    public function testIsEmpty()
    {
        foreach (array(201, 204, 304) as $code)
        {
            $response = new Response('', $code);
            $this->assertTrue($response->isEmpty());
        }

        $response = new Response('', 200);
        $this->assertFalse($response->isEmpty());
    }

    public function testIsForbidden()
    {
        $response = new Response('', 403);
        $this->assertTrue($response->isForbidden());

        $response = new Response('', 200);
        $this->assertFalse($response->isForbidden());
    }

    public function testIsOk()
    {
        $response = new Response('', 200);
        $this->assertTrue($response->isOk());

        $response = new Response('', 404);
        $this->assertFalse($response->isOk());
    }

    public function testIsServerOrClientError()
    {
        $response = new Response('', 404);
        $this->assertTrue($response->isClientError());
        $this->assertFalse($response->isServerError());

        $response = new Response('', 500);
        $this->assertFalse($response->isClientError());
        $this->assertTrue($response->isServerError());
    }

    public function testHasVary()
    {
        $response = new Response();
        $this->assertFalse($response->hasVary());

        $response->setVary('User-Agent');
        $this->assertTrue($response->hasVary());
    }

    public function testSetEtag()
    {
        $response = new Response('', 200, array('ETag' => '"12345"'));
        $response->setEtag();

        $this->assertNull($response->headers->get('Etag'), '->setEtag() removes Etags when call with null');
    }

    protected function createDateTimeOneHourAgo()
    {
        $date = new \DateTime();

        return $date->sub(new \DateInterval('PT1H'));
    }

    protected function createDateTimeOneHourLater()
    {
        $date = new \DateTime();

        return $date->add(new \DateInterval('PT1H'));
    }

    protected function createDateTimeNow()
    {
        return new \DateTime();
    }
}
