<?php

namespace Clue\Redis\Server\Type;

use SplDoublyLinkedList;
use Evenement\EventEmitterTrait;

class RedisList extends SplDoublyLinkedList
{
	use EventEmitterTrait;
	
	public function unshift($value)
	{
		$rc = parent::unshift($value);
		$this->emit('unshift', [$this, $value]);
		return $rc;
	}
	
	public function push($value)
	{
		$rc = parent::push($value);
		$this->emit('push', [$this, $value]);
		return $rc;
	}
}