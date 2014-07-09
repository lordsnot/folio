<?php

namespace Wap\Sites\FatAsButter\Controller;

class Main extends Butter
{
	public $defaultAction = 'home';

	/**
	 * @resource home
	 */
	public function actionHome($request)
	{
		return $this->render('home');
	}

	/**
	 * @resource register
	 */
	public function actionRegister($request)
	{
		return $this->render('register');
	}

	/**
	 * @resource venue
	 */
	public function actionVenue($request)
	{
		return $this->render('venue');
	}

	/**
	 * @resource timetable
	 */
	public function actionTimetable($request)
	{
		$timetable = $this->dataLayer->getTimetable();

		return $this->render('timetable', array(
			'timetableData' => $timetable,
		));
	}

	/**
	 * @resource transport
	 */
	public function actionTransport($request)
	{
		return $this->render('transport');
	}

	/**
	 * @resource entry
	 */
	public function actionEntry($request)
	{
		return $this->render('entry');
	}

	/**
	 * @resource faq
	 */
	public function actionFaq($request)
	{
		return $this->render('faq');
	}

	/**
	 * @resource submit
	 */
	public function actionSubmit($request)
	{
		$data = array();
		$data = $this->dataLayer->validateForm();

		//success
		if(!$data) {
			header("Location: ".$this->controller->serviceUrl('main/thankyou'));
		}
		//fail
		else {
			return $this->render('home', array(
				'errors' => $data['errors'],
				'data' => $data['data']
			));
		}
	}

	/**
	 * @resource lineup
	 */
	public function actionLineup($request)
	{
		$data = array();

		$data['lineup'] = $this->dataLayer->getLineup();

		return $this->render('lineup', array(
			'lineup' => $data['lineup'],
		));
	}

	/**
	 * @resource artist
	 */
	public function actionArtist($request)
	{
		$data = array();
		$artist = $request->get['artist'];

		$data['artist'] = $this->dataLayer->getArtist($artist);

		if (!$data['artist']){
			return $this->front->raiseError(404);
		}

		return $this->render('artist', array(
			'artist' => $data['artist'],
		));
	}

	/**
	 * @resource thankyou
	 */
	public function actionThankyou($request)
	{
		return $this->render('thankyou');
	}

	/**
	 * @resource terms
	 */
	public function actionTerms($request)
	{
		return $this->render('terms');
	}
}
