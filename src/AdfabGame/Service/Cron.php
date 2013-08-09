<?php

namespace AdfabGame\Service;

use AdfabGame\Entity\Entry;

use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;
use ZfcBase\EventManager\EventProvider;
use AdfabGame\Options\ModuleOptions;
use AdfabGame\Mapper\GameInterface as GameMapperInterface;

class Cron extends EventProvider implements ServiceManagerAwareInterface
{

    protected $leaderBoardService;

    /**
     * @var GameMapperInterface
     */
    protected $gameMapper;

    /**
     * @var EntryMapperInterface
     */
    protected $entryMapper;

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var UserServiceOptionsInterface
     */
    protected $options;

    //TODO : Terminer et Refactorer afin de le mettre dans AdfabCore
    public static function cronMail()
    {
        //TODO : factoriser la config
        $configuration = require 'config/application.config.php';
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : array();
        $sm = new \Zend\ServiceManager\ServiceManager(new \Zend\Mvc\Service\ServiceManagerConfig($smConfig));
        $sm->setService('ApplicationConfig', $configuration);
        $sm->get('ModuleManager')->loadModules();
        $sm->get('Application')->bootstrap();

        $mailService = $sm->get('adfabuser_message');
        $gameService = $sm->get('adfabgame_quiz_service');
        $options = $sm->get('adfabgame_module_options');

        $from    = "admin@playground.fr";//$options->getEmailFromAddress();
        $subject = "sujet game"; //$options->getResetEmailSubjectLine();

        $to = "gbesson@test.com";

        $game = $gameService->checkGame('qooqo');

        // On recherche les joueurs qui n'ont pas partagé leur qquiz après avoir joué
        // entry join user join game : distinct user et game et game_entry = 0 et updated_at <= jour-1 et > jour - 2
        //$contacts = getQuizUsersNotSharing();

        //foreach ($contacts as $contact) {
            //$message = $mailService->createTextMessage('titi@test.com', 'gbesson@test.com', 'sujetcron', 'adfab-user/email/forgot', array());
            $message = $mailService->createTextMessage($from, $to, $subject, 'adfab-game/frontend/email/share_reminder', array('game' => $game));

            $mailService->send($message);
        //}

    }

    public static function instantWinEmail()
    {
        //TODO : factoriser la config
        $configuration = require 'config/application.config.php';
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : array();
        $sm = new \Zend\ServiceManager\ServiceManager(new \Zend\Mvc\Service\ServiceManagerConfig($smConfig));
        $sm->setService('ApplicationConfig', $configuration);
        $sm->get('ModuleManager')->loadModules();
        $sm->get('Application')->bootstrap();

		$skinUrl = $sm->get('ViewRenderer')->url('home', array(), array('force_canonical' => true));
        $mailService = $sm->get('adfabuser_message');
        $gameService = $sm->get('adfabgame_instantwin_service');
        $options = $sm->get('adfabgame_module_options');

		$from = $options->getEmailFromAddress();
        $subject = "Votre jeu Instant gagnant";

        // Je recherche les jeux instantwin en cours
        $games = $gameService->getActiveGames(false, 'instantwin');


		$today = new \DateTime('now');
		$today = $today->format('Y-m-d');

        // Je recherche les joueurs qui ont deja joué une seule fois au jeu mais pas rejoué dans le laps autorisé
        $arrayUsers = array();
        foreach ($games as $game) {
            $entries = $gameService->getEntryMapper()->findPlayersWithOneEntryBy($game);
            foreach ($entries as $e) {
                $arrayUsers[$e->getUser()->getId()]['user'] = $e->getUser();
                $arrayUsers[$e->getUser()->getId()]['game'] = $game;
				$arrayUsers[$e->getUser()->getId()]['created_at'] = $e->getCreatedAt()->format('Y-m-d');
				$arrayUsers[$e->getUser()->getId()]['send_at'] = $e->getCreatedAt()->add(new \DateInterval('P2D'))->format('Y-m-d');
				
            }
        }

        // J'envoie un mail de relance 1 seule fois à +2j
        foreach ($arrayUsers as $k => $entry) {
        	if($entry['send_at'] == $today){
        		$user = $entry['user'];
	            $game = $entry['game'];
	           	$message = $mailService->createHtmlMessage($from, $user->getEmail(), $subject, 'adfab-game/frontend/email/game_instantwin_reminder', array('game' => $game, 'user' => $user, 'skinUrl' => $skinUrl));
	           	$mailService->send($message);
        	}
        }
    }

	public static function postVoteEmail()
    {
        $configuration = require 'config/application.config.php';
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : array();
        $sm = new \Zend\ServiceManager\ServiceManager(new \Zend\Mvc\Service\ServiceManagerConfig($smConfig));
        $sm->setService('ApplicationConfig', $configuration);
        $sm->get('ModuleManager')->loadModules();
        $sm->get('Application')->bootstrap();


		$skinUrl = $sm->get('ViewRenderer')->url('home', array(), array('force_canonical' => true));
        $mailService = $sm->get('adfabuser_message');
        $gameService = $sm->get('adfabgame_postvote_service');
        $options = $sm->get('adfabgame_module_options');

		$from = $options->getEmailFromAddress();
        $subject = "Invitez vos amis à voter pour vous";
		
		$games = $gameService->getActiveGames(false, 'postvote');
		
        $arrayUsers = array();
        foreach ($games as $game) {
           	$posts = $gameService->findArrayOfValidatedPosts($game, 'vote', '');
            foreach ($posts as $post) {
                $arrayUsers[$post['user']->getId()]['post'] = $post;
                $arrayUsers[$post['user']->getId()]['game'] = $game;
            }
        }

		$today = new \DateTime('now');
		$today = $today->format('Y-m-d');
		
		// J'envoie un mail de relance 1 seule fois à +1j
        foreach ($arrayUsers as $k => $entry) {
            $game = $entry['game'];
			$post = $entry['post'];
			$user = $post['user'];
			$sendAt = $post['created_at']->add(new \DateInterval('P1D'))->format('Y-m-d');
			
			if($sendAt == $today){
			
            	$message = $mailService->createHtmlMessage($from, $user->getEmail(), $subject, 'adfab-game/frontend/email/postvote_reminder', array('game' => $game, 'user' => $user, 'post' => $post, 'skinUrl' => $skinUrl));
            	$mailService->send($message);
			}
        }
    }

    /**
     * getGameMapper
     *
     * @return GameMapperInterface
     */
    public function getGameMapper()
    {
        if (null === $this->gameMapper) {
            $this->gameMapper = $this->getServiceManager()->get('adfabgame_game_mapper');
        }

        return $this->gameMapper;
    }

    /**
     * setGameMapper
     *
     * @param  GameMapperInterface $gameMapper
     * @return User
     */
    public function setGameMapper(GameMapperInterface $gameMapper)
    {
        $this->gameMapper = $gameMapper;

        return $this;
    }

    /**
     * getEntryMapper
     *
     * @return EntryMapperInterface
     */
    public function getEntryMapper()
    {
        if (null === $this->entryMapper) {
            $this->entryMapper = $this->getServiceManager()->get('adfabgame_entry_mapper');
        }

        return $this->entryMapper;
    }

    /**
     * setEntryMapper
     *
     * @param  EntryMapperInterface $entryMapper
     * @return Entry
     */
    public function setEntryMapper($entryMapper)
    {
        $this->entryMapper = $entryMapper;

        return $this;
    }

    public function setOptions(ModuleOptions $options)
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions()
    {
        if (!$this->options instanceof ModuleOptions) {
            $this->setOptions($this->getServiceManager()->get('adfabgame_module_options'));
        }

        return $this->options;
    }

    public function getLeaderBoardService()
    {
        if (!$this->leaderBoardService) {
            $this->leaderBoardService = $this->getServiceManager()->get('adfabgame_leaderboard_service');
        }

        return $this->leaderBoardService;
    }

    public function setLeaderBoardService(LeaderBoardService $leaderBoardService)
    {
        $this->leaderBoardService = $leaderBoardService;

        return $this;
    }

    /**
     * Retrieve service manager instance
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Set service manager instance
     *
     * @param  ServiceManager $serviceManager
     * @return Game
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;

        return $this;
    }
}
