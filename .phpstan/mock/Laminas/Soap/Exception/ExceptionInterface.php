<?php

namespace Laminas\Soap\Exception;

interface ExceptionInterface
{
	/**
	 * @return string
	 * @throws void
	 */
	public function getMessage();

	/**
	 * @return mixed
	 * @throws void
	 */
	public function getCode();
}
