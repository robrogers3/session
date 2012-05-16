<?php

use Symfony\Component\HttpFoundation\Request;

class StoreTest extends PHPUnit_Framework_TestCase {

	public function testValidSessionIsSet()
	{
		$store = $this->storeMock('isInvalid');
		$session = $this->dummySession();
		$request = Request::create('/', 'GET', array(), array('illuminate_session' => 'foo'));
		$store->expects($this->once())->method('retrieveSession')->with($this->equalTo('foo'))->will($this->returnValue($session));
		$store->expects($this->once())->method('isInvalid')->with($this->equalTo($session))->will($this->returnValue(false));
		$store->start($request);
		$this->assertEquals($session, $store->getSession());
	}


	public function testInvalidSessionCreatesFresh()
	{
		$store = $this->storeMock('isInvalid');
		$session = $this->dummySession();
		$request = Request::create('/', 'GET', array(), array('illuminate_session' => 'foo'));
		$store->expects($this->once())->method('retrieveSession')->with($this->equalTo('foo'))->will($this->returnValue($session));
		$store->expects($this->once())->method('isInvalid')->with($this->equalTo($session))->will($this->returnValue(true));
		$store->start($request);

		$session = $store->getSession();
		$this->assertFalse($store->sessionExists());
		$this->assertTrue(strlen($session['id']) == 40);
		$this->assertFalse(isset($session['last_activity']));
	}


	public function testOldSessionsAreConsideredInvalid()
	{
		$store = $this->storeMock('createFreshSession');
		$request = Request::create('/', 'GET', array(), array('illuminate_session' => 'foo'));
		$session = $this->dummySession();
		$session['last_activity'] = '1111111111';
		$store->expects($this->once())->method('retrieveSession')->with($this->equalTo('foo'))->will($this->returnValue($session));
		$store->expects($this->once())->method('createFreshSession');
		$store->start($request);
	}


	public function testNullSessionsAreConsideredInvalid()
	{
		$store = $this->storeMock('createFreshSession');
		$request = Request::create('/', 'GET', array(), array('illuminate_session' => 'foo'));
		$store->expects($this->once())->method('retrieveSession')->with($this->equalTo('foo'))->will($this->returnValue(null));
		$store->expects($this->once())->method('createFreshSession');
		$store->start($request);
	}


	public function testBasicPayloadManipulation()
	{
		$store = $this->storeMock('isInvalid');
		$request = Request::create('/', 'GET', array(), array('illuminate_session' => 'foo'));
		$store->expects($this->once())->method('isInvalid')->will($this->returnValue(true));
		$store->start($request);

		$store->put('foo', 'bar');
		$this->assertEquals('bar', $store->get('foo'));
		$this->assertTrue($store->has('foo'));
		$store->forget('foo');
		$this->assertFalse($store->has('foo'));
		$this->assertEquals('taylor', $store->get('bar', 'taylor'));
		$this->assertEquals('taylor', $store->get('bar', function() { return 'taylor'; }));
	}


	public function testFlashDataCanBeRetrieved()
	{
		$store = $this->storeMock();
		$store->setSession(array('id' => '1', 'data' => array(':new:' => array('foo' => 'bar'), ':old:' => array('baz' => 'boom'))));
		$this->assertEquals('bar', $store->get('foo'));
		$this->assertEquals('boom', $store->get('baz'));
	}


	public function testFlashMethodPutsDataInNewArray()
	{
		$store = $this->storeMock();
		$store->setSession(array('id' => '1', 'data' => array(':new:' => array(), ':old:' => array())));
		$store->flash('foo', 'bar');
		$session = $store->getSession();
		$this->assertEquals('bar', $session['data'][':new:']['foo']);
	}


	public function testReflashMethod()
	{
		$store = $this->storeMock();
		$store->setSession(array('id' => '1', 'data' => array(':new:' => array(), ':old:' => array('foo' => 'bar'))));
		$store->reflash();
		$session = $store->getSession();
		$this->assertEquals(array('foo' => 'bar'), $session['data'][':new:']);
	}


	public function testKeepMethod()
	{
		$store = $this->storeMock();
		$store->setSession(array('id' => '1', 'data' => array(':new:' => array(), ':old:' => array('foo' => 'bar', 'baz' => 'boom'))));
		$store->keep(array('foo'));
		$session = $store->getSession();
		$this->assertEquals(array('foo' => 'bar'), $session['data'][':new:']);
	}


	public function testFlushMethod()
	{
		$store = $this->storeMock(array('createData'));
		$store->setSession(array('id' => '1', 'data' => array(':new:' => array('foo' => 'bar'))));
		$store->expects($this->once())->method('createData');
		$store->flush();
	}


	public function testArrayAccess()
	{
		$store = $this->storeMock();
		$store->setSession(array('id' => '1', 'data' => array()));

		$store['foo'] = 'bar';
		$this->assertEquals('bar', $store['foo']);
		unset($store['foo']);
		$this->assertFalse(isset($store['foo']));
	}


	public function testRegenerateMethod()
	{
		$store = $this->storeMock();
		$store->setSession(array('id' => '1'));
		$store->regenerateSession();
		$session = $store->getSession();
		$this->assertTrue(strlen($session['id']) == 40);
		$this->assertFalse($store->sessionExists());
	}


	public function testFinishMethodCallsUpdateMethodAndAgesFlashData()
	{
		$store = $this->storeMock('getCurrentTime');
		$store->setSession($session = array('id' => '1', 'data' => array(':old:' => array('foo' => 'bar'), ':new:' => array('baz' => 'boom'))));
		$store->expects($this->any())->method('getCurrentTime')->will($this->returnValue(1));
		$session['last_activity'] = 1;
		$session['data'] = array(':old:' => array('baz' => 'boom'), ':new:' => array());
		$store->expects($this->once())->method('updateSession')->with($this->equalTo('1'), $this->equalTo($session));
		$response = new Symfony\Component\HttpFoundation\Response;
		$store->finish($response);
	}


	public function testFinishMethodCallsCreateMethodAndAgesFlashData()
	{
		$store = $this->storeMock('getCurrentTime');
		$store->setSession($session = array('id' => '1', 'data' => array(':old:' => array('foo' => 'bar'), ':new:' => array('baz' => 'boom'))));
		$store->expects($this->any())->method('getCurrentTime')->will($this->returnValue(1));
		$session['last_activity'] = 1;
		$session['data'] = array(':old:' => array('baz' => 'boom'), ':new:' => array());
		$store->expects($this->once())->method('createSession')->with($this->equalTo('1'), $this->equalTo($session));
		$store->setExists(false);
		$response = new Symfony\Component\HttpFoundation\Response;
		$store->finish($response);	
	}


	public function testFinishMethodSetsCookieUsingOptions()
	{
		$store = $this->storeMock('getCurrentTime');
		$store->setSession($session = array('id' => '1', 'data' => array(':old:' => array('foo' => 'bar'), ':new:' => array('baz' => 'boom'))));
		$response = new Symfony\Component\HttpFoundation\Response;
		$store->expects($this->any())->method('getCurrentTime')->will($this->returnValue(1));
		$store->setCookieOption('path', '//');
		$store->setCookieOption('domain', 'foo.com');
		$store->finish($response);

		$cookies = $response->headers->getCookies();
		$this->assertTrue(count($cookies) === 1);
		$cookie = $cookies[0];
		$this->assertEquals('1', $cookie->getValue());
		$this->assertEquals('foo.com', $cookie->getDomain());
		$this->assertEquals('//', $cookie->getPath());
		$this->assertEquals('illuminate_session', $cookie->getName());
		$expiration = 1 + (120 * 60);
		$this->assertEquals($expiration, $cookie->getExpiresTime());
	}


	protected function dummySession()
	{
		return array('id' => '123', 'data' => array(':old:' => array(), ':new:' => array()), 'last_activity' => '9999999999');
	}


	protected function storeMock($stub = array())
	{
		$stub = array_merge((array) $stub, array('retrieveSession', 'createSession', 'updateSession'));
		return $this->getMock('Illuminate\Session\Store', $stub);
	}

}