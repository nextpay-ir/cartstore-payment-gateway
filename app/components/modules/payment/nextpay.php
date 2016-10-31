<?php

namespace app\components\modules\payment;

use framework\request\Request;

/**
 * payment with nextpay
 *
 * @author		Nextpay.ir groups <info@nextpay.ir>
 * @since		1.0
 * @package		payment module
 * @copyright   (c) 2016 all rights reserved
 */
class Nextpay extends Payment
{
	/**
	 * request to nextpay gateway
	 *
	 * @param integer $id, trans id (primary key)
	 * @param integer $order_id, nextpay trans_id
	 * @param integer $price, trans price
	 * @param array $module, information of this module
	 * @param integer $product, product id
	 *
	 * @access public
	 * @return void
	 */
	public function request($id, $order_id, $price, $module, $product )
	{
		\Framework::import( BASEPATH . 'app/extensions/nusoap', true );

		$params = [
			'api_key' => $module['apiKey']['value'],
			'amount' => $price,
			'order_id' => $order_id,
			'callback_uri' => $this->getCallbackUrl( $order_id )
		];

		$client = new \nusoap_client( 'http://api.nextpay.org/gateway/token.wsdl', 'wsdl' );
		$client->soap_defencoding = 'UTF-8';
		$result = $client->call( 'TokenGenerator',[ $params ] );

        $result = $result['TokenGeneratorResult'] ;
		
		if( $error = $client->getError() ) {
			$this->setFlash( 'danger', $error );
		} elseif( $client->fault ) {
			$this->setFlash( 'danger', $client->faultcode.':'.$client->faultstring );
		} elseif( isset( $result['code'], $result['trans_id'] ) and $result['code'] == -1 ) {
			$this->updateAu( $id, $result['trans_id'] );
			$this->redirect( 'http://api.nextpay.org/gateway/payment/' . $result['trans_id'] );
		} else {
			$this->setFlash( 'danger', $result['code'] );
		}
	}
	
	/**
	 * request to nextpay for verify transaction
	 *
	 * @param integer $id, trans id (primary key)
	 * @param integer $order_id, nextpay trans_id
	 * @param integer $price, trans price
	 * @param array $module, information of this module
	 * @param integer $product, product id
	 *
	 * @access public
	 * @return array|boolean
	 */
	public function verify($id, $order_id, $price, $module, $product )
	{
		if( !Request::isPost( 'trans_id' ) OR !Request::isPost( 'order_id' ) OR  Request::getPost( 'order_id' != $order_id )) {
			$this->setFlash( 'danger', $this->lang()->getIndex( 'nextpay', 'inputNotValid' ) );
			return false;
		}

		\Framework::import( BASEPATH . 'app/extensions/nusoap', true );

		$params = [
			'api_key' => $module['apiKey']['value'],
			'trans_id' => Request::getPost( 'trans_id' ),
			'amount' => $price ,
            'order_id' => $order_id
		];

		$client = new \nusoap_client( 'http://api.nextpay.org/gateway/verify.wsdl', 'wsdl' );
		$client->soap_defencoding = 'UTF-8';

		$result = $client->call( 'PaymentVerification',[ $params ] );

        $result = $result['PaymentVerificationResult'];
		
		if( $error = $client->getError() ) {
			$this->setFlash( 'danger', $error );
		} elseif( $client->fault ) {
			$this->setFlash( 'danger', $client->faultcode.':'.$client->faultstring );
		} elseif( isset( $result['code'] ) and $result['code'] == 0 ) {
			return [ 'au' => $order_id ];
		} else {
			$this->setFlash( 'danger', 'تراکتش نا موفق : ' . $result['code'] );
		}
	}

	/**
	 * module fields for install this
	 *
	 * @access public
	 * @return array
	 */
	public function fields()
	{
		return [
			'apiKey' => [
				'label' => $this->lang()->getIndex( 'nextpay', 'apiKey' ),
				'value' => '',
			],
		];
	}
}