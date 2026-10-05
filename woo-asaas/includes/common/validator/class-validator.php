<?php

namespace WC_Asaas\Common\Validator;

abstract class Validator {

	protected $errors = array();

	protected $exception_message = '';

	protected function validate( $data ) {
		$this->maybe_throws_exception();
	}

	private function maybe_throws_exception() {
		if ( 0 < count( $this->errors ) ) {
			throw new Validation_Exception( esc_html( $this->exception_message ), 0, null, array_map( 'esc_html', $this->errors ) );
		}
	}
}
