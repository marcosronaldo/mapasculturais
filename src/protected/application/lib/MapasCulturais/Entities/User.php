<?php

namespace MapasCulturais\Entities;

use Doctrine\ORM\Mapping as ORM;
use MapasCulturais\App;

/**
 * User
 *
 * @property-read \MapasCulturais\Entities\Agent[] $agents Active Agents
 * @property-read \MapasCulturais\Entities\Space[] $spaces Active Spaces
 * @property-read \MapasCulturais\Entities\Project[] $projects Active Projects
 * @property-read \MapasCulturais\Entities\Event[] $events Active Events
 * @property-read \MapasCulturais\Entities\Subsite[] $subsite Active Subsite
 * @property-read \MapasCulturais\Entities\Seal[] $seals Active Seals
 *
 * @property-read \MapasCulturais\Entities\Agent $profile User Profile Agent
 *
 * @ORM\Table(name="usr")
 * @ORM\Entity
 * @ORM\entity(repositoryClass="MapasCulturais\Repositories\User")
 * @ORM\HasLifecycleCallbacks
 */
class User extends \MapasCulturais\Entity implements \MapasCulturais\UserInterface{
    const STATUS_ENABLED = 1;

    use \MapasCulturais\Traits\EntityMetadata;


    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="usr_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="auth_provider", type="smallint", nullable=false)
     */
    protected $authProvider;

    /**
     * @var string
     *
     * @ORM\Column(name="auth_uid", type="string", length=512, nullable=false)
     */
    protected $authUid;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=false)
     */
    protected $email;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_login_timestamp", type="datetime", nullable=true)
     */
    protected $lastLoginTimestamp;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_timestamp", type="datetime", nullable=false)
     */
    protected $createTimestamp;

    /**
     * @var integer
     *
     * @ORM\Column(name="status", type="smallint", nullable=false)
     */
    protected $status = self::STATUS_ENABLED;


    /**
     *
     * @var \MapasCulturais\Entities\Role[] User Roles
     * @ORM\OneToMany(targetEntity="MapasCulturais\Entities\Role", mappedBy="user", cascade="remove", orphanRemoval=true, fetch="LAZY")
     */
    protected $roles;

    /**
     * @ORM\OneToMany(targetEntity="MapasCulturais\Entities\Agent", mappedBy="user", cascade="remove", orphanRemoval=true, fetch="LAZY")
     * @ORM\OrderBy({"createTimestamp" = "ASC"})
     */
    protected $agents;

    /**
     * @var \MapasCulturais\Entities\Agent
     *
     * @ORM\ManyToOne(targetEntity="MapasCulturais\Entities\Agent", fetch="LAZY")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="profile_id", referencedColumnName="id")
     * })
     */
    protected $profile;

    /**
    * @ORM\OneToMany(targetEntity="MapasCulturais\Entities\UserMeta", mappedBy="owner", cascade={"remove","persist"}, orphanRemoval=true)
    */
    protected $__metadata;


    public function __construct() {
        parent::__construct();

        $this->agents = new \Doctrine\Common\Collections\ArrayCollection();
        $this->lastLoginTimestamp = new \DateTime;
    }
    
    public function getEntityTypeLabel($plural = false) {
        if ($plural)
            return \MapasCulturais\i::__('Usuários');
        else
            return \MapasCulturais\i::__('Usuário');
    }

    function getOwnerUser(){
        return $this;
    }

    function setAuthProvider($provider_name){
        $this->authProvider = App::i()->getRegisteredAuthProviderId($provider_name);
    }

    function setProfile(Agent $agent){
        $this->checkPermission('changeProfile');

        if(!$this->equals($agent->user))
            throw new \Exception ('error');

        $this->profile = $agent;

        $agent->setParentAsNull(true);
    }

    function jsonSerialize() {
        $result = parent::jsonSerialize();
        $result['profile'] = $this->profile->simplify('id,name,type,terms,avatar,singleUrl');
        unset($result['authUid']);
        return $result;
    }

    function addRole($role_name, $subsite_id = false){
        $app = App::i();

        $subsite_id = $subsite_id === false ? $app->getCurrentSubsiteId() : $subsite_id;

        if(method_exists($this, 'canUserAddRole' . $role_name))
            $this->checkPermission('addRole' . $role_name);
        else
            $this->checkPermission('addRole');

        if(!$this->is($role_name, $subsite_id)){
            $role = new Role;
            $role->user = $this;
            $role->name = $role_name;
            $role->subsiteId = $subsite_id;
            $role->save(true);
            return true;
        }

        return false;
    }

    function removeRole($role_name, $subsite_id = false){
        $app = App::i();
        $subsite_id = $subsite_id === false ? $app->getCurrentSubsiteId() : $subsite_id;

        if(method_exists($this, 'canUserRemoveRole' . $role_name))
            $this->checkPermission('removeRole' . $role_name);
        else
            $this->checkPermission('removeRole');
        
        foreach($this->roles as $role){
            if($role->name == $role_name && $role->subsiteId == $subsite_id){
                $role->delete(true);
                return true;
            }
        }

        return false;
    }

    function can($action, \MapasCulturais\Entity $entity){
        return $entity->canUser($action, $this);
    }

    protected function canUserAddRole($user){
        return $user->is('admin') && $user->id != $this->id;
    }

    protected function canUserAddRoleAdmin($user){
        return $user->is('superAdmin') && $user->id != $this->id;
    }

    protected function canUserAddRoleSuperAdmin($user){
        return $user->is('superAdmin') && $user->id != $this->id;
    }

    protected function canUserAddRoleSaasAdmin($user){
        return $user->is('saasSuperAdmin') && $user->id != $this->id;
    }

    protected function canUserAddRoleSaasSuperAdmin($user){
        return $user->is('saasSuperAdmin') && $user->id != $this->id;
    }


    protected function canUserRemoveRole($user){
        return $user->is('admin') && $user->id != $this->id;
    }

    protected function canUserRemoveRoleAdmin($user){
        return $user->is('superAdmin') && $user->id != $this->id;
    }

    protected function canUserRemoveRoleSuperAdmin($user){
        return $user->is('superAdmin') && $user->id != $this->id;
    }

    protected function canUserRemoveRoleSaasAdmin($user){
        return $user->is('saasSuperAdmin') && $user->id != $this->id;
    }

    protected function canUserRemoveRoleSaasSuperAdmin($user){
        return $user->is('saasSuperAdmin') && $user->id != $this->id;
    }

    function is($role_name, $subsite_id = false){
        if($role_name === 'admin' && $this->is('superAdmin')){
            return true;
        }

        if($role_name === 'superAdmin' && $this->is('saasAdmin')){
            return true;
        }

        if($role_name === 'saasAdmin' && $this->is('saasSuperAdmin')){
            return true;
        }

        $app = App::i();

        if($role_name === 'saasAdmin' || $role_name === 'saasSuperAdmin'){
            $subsite_id = null;
        } else {
            if (false === $subsite_id) {
                $subsite_id = $app->getCurrentSubsiteId();
            }
        }


        foreach ($this->roles as $role) {
            if ($role->name == $role_name && $role->subsiteId === $subsite_id) {
                return true;
            }
        }

        return false;
    }

    protected function canUserCreate($user = null){
        // only guest user can create
        return is_null($user) || $user->is('guest');
    }

    protected function _getEntitiesByStatus($entityClassName, $status = 0, $status_operator = '>'){
    	if ($entityClassName::usesTaxonomies()) {
    		$dql = "
	    		SELECT
	    			e, m, tr
	    		FROM
	    			$entityClassName e
	    			JOIN e.owner a
	    			LEFT JOIN e.__metadata m
	    			LEFT JOIN e.__termRelations tr
	    		WHERE
	    			e.status $status_operator :status AND
	    			a.user = :user
	    		ORDER BY
	    			e.name,
	    			e.createTimestamp ASC ";
    	} else {
    		$dql = "
    			SELECT
   		 			e, m
    			FROM
    				$entityClassName e
		    		JOIN e.owner a
		    		LEFT JOIN e.__metadata m
	    		WHERE
		    		e.status $status_operator :status AND
		    		a.user = :user
	    		ORDER BY
		    		e.name,
		    		e.createTimestamp ASC ";
    	}

		$query = App::i()->em->createQuery($dql);
        $query->setParameter('user', $this);
        $query->setParameter('status', $status);

        $entityList = $query->getResult();
        return $entityList;
    }

    private function _getAgentsByStatus($status){
        return App::i()->repo('Agent')->findBy(['user' => $this, 'status' => $status], ['name' => "ASC"]);
    }

    function getEnabledAgents(){
        return $this->_getAgentsByStatus(Agent::STATUS_ENABLED);
    }
    function getDraftAgents(){
        $this->checkPermission('modify');

        return $this->_getAgentsByStatus(Agent::STATUS_DRAFT);
    }
    function getTrashedAgents(){
        $this->checkPermission('modify');

        return $this->_getAgentsByStatus(Agent::STATUS_TRASH);
    }
    function getDisabledAgents(){
        $this->checkPermission('modify');

        return $this->_getAgentsByStatus(Agent::STATUS_DISABLED);
    }
    function getInvitedAgents(){
        $this->checkPermission('modify');

        return $this->_getAgentsByStatus(Agent::STATUS_INVITED);
    }
    function getRelatedAgents(){
        $this->checkPermission('modify');

        return $this->_getAgentsByStatus(Agent::STATUS_RELATED);
    }

    function getArchivedAgents(){
        $this->checkPermission('modify');

        return $this->_getAgentsByStatus( Agent::STATUS_ARCHIVED);
    }


    function getHasControlAgents(){
        $this->checkPermission('modify');

        if(!($agents = App::i()->repo('Agent')->findByAgentRelationUser($this, true)))
            $agents = [];

        return $agents;
    }

    function getAgentWithControl() {
        $this->checkPermission('modify');
        $app = App::i();
        $entity = $app->view->controller->id;
        $agents = $app->repo($entity)->findByAgentWithEntityControl();
        if(!$agents)
            $agents = [];
        return $agents;
    }

    public function getSpaces(){
        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Space');
    }
    function getEnabledSpaces(){
        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Space', Space::STATUS_ENABLED, '=');
    }
    function getDraftSpaces(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Space', Space::STATUS_DRAFT, '=');
    }
    function getTrashedSpaces(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Space', Space::STATUS_TRASH, '=');
    }
    function getDisabledSpaces(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Space', Space::STATUS_DISABLED, '=');
    }

    function getArchivedSpaces(){
        $this->checkPermission('modify');
        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Space', Space::STATUS_ARCHIVED,'=');
    }

    function getHasControlSpaces(){
        $this->checkPermission('modify');
        
        if(!($spaces = App::i()->repo('Space')->findByAgentRelationUser($this, true)))
            $spaces = [];

        return $spaces;
    }

    public function getEvents(){
        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Event');
    }
    function getEnabledEvents(){
        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Event', Event::STATUS_ENABLED, '=');
    }
    function getDraftEvents(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Event', Event::STATUS_DRAFT, '=');
    }
    function getTrashedEvents(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Event', Event::STATUS_TRASH, '=');
    }
    function getDisabledEvents(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Event', Event::STATUS_DISABLED, '=');
    }

    function getHasControlEvents(){
        $this->checkPermission('modify');
        if(!($events = App::i()->repo('Event')->findByAgentRelationUser($this, true)))
            $events = [];
        return $events;
    }

    function getArchivedEvents(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Event', Event::STATUS_ARCHIVED,'=');
    }

    public function getProjects(){
        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Project');
    }
    function getEnabledProjects(){
        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Project', Project::STATUS_ENABLED, '=');
    }
    function getDraftProjects(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Project', Project::STATUS_DRAFT, '=');
    }
    function getTrashedProjects(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Project', Project::STATUS_TRASH, '=');
    }
    function getDisabledProjects(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Project', Project::STATUS_DISABLED, '=');
    }

    function getArchivedProjects(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Project', Project::STATUS_ARCHIVED,'=');
    }

    function getHasControlProjects(){
        $this->checkPermission('modify');

        $projects = App::i()->repo('Project')->findByAgentRelationUser($this, true);

        if(!$projects)
            $projects = [];

        return $projects;
    }

    public function getSubsite($status = null) {
        $result = [];

        if ($this->is('saasAdmin')) {
            $subsites = App::i()->repo('Subsite')->findAll();

            foreach ($subsites as $subsite) {
                if (!is_null($status) && $subsite->status == $status) {
                    $result[] = $subsite;
                } else if ($subsite->status > 0) {
                    $result[] = $subsite;
                }
            }
        }

        return $result;
    }

    function getEnabledSubsite(){
        return $this->getSubsite(Subsite::STATUS_ENABLED);
    }
    function getDraftSubsite(){
        $this->checkPermission('modify');

        return $this->getSubsite(Subsite::STATUS_DRAFT);
    }
    function getTrashedSubsite(){
        $this->checkPermission('modify');

        return $this->getSubsite(Subsite::STATUS_TRASH);
    }
    function getDisabledSubsite(){
        $this->checkPermission('modify');

        return $this->getSubsite(Subsite::STATUS_DISABLED);
    }
    function getArchivedSubsite(){
        $this->checkPermission('modify');

        return $this->getSubsite(Subsite::STATUS_ARCHIVED);
    }

    public function getSeals(){
    	return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Seal');
    }
    function getEnabledSeals(){
    	return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Seal', Seal::STATUS_ENABLED, '=');
    }
    function getDraftSeals(){
    	$this->checkPermission('modify');

    	return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Seal', Seal::STATUS_DRAFT, '=');
    }
    function getTrashedSeals(){
    	$this->checkPermission('modify');

    	return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Seal', Seal::STATUS_TRASH, '=');
    }
    function getDisabledSeals(){
    	$this->checkPermission('modify');

    	return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Seal', Seal::STATUS_DISABLED, '=');
    }

    function getArchivedSeals(){
        $this->checkPermission('modify');

        return $this->_getEntitiesByStatus(__NAMESPACE__ . '\Seal', Seal::STATUS_ARCHIVED,'=');
    }

    function getHasControlSeals(){
        $this->checkPermission('modify');

        if(!($seals = App::i()->repo('Seal')->findByAgentRelationUser($this, true)))
            $seals = [];

        return $seals;
    }

    function getNotifications($status = null){
        $app = App::i();
        $app->em->clear('MapasCulturais\Entities\Notification');

        if(is_null($status)){
            $status_operator =  '>';
            $status = '0';

        }else{
            $status_operator =  '=';
        }
        $dql = "
            SELECT
                e
            FROM
                MapasCulturais\Entities\Notification e
            WHERE
                e.status $status_operator :status AND
                e.user = :user
            ORDER BY
                e.createTimestamp DESC
        ";
        $query = App::i()->em->createQuery($dql);

        $query->setParameter('user', $this);
        $query->setParameter('status', $status);

        $entityList = $query->getResult();
        return $entityList;

    }

    function getEntitiesNotifications($app) {
      if(in_array('notifications',$app->config['plugins.enabled']) && $app->config['notifications.user.access'] > 0) {
        $now = new \DateTime;
        $interval = date_diff($app->user->lastLoginTimestamp, $now);
        if($interval->format('%a') >= $app->config['notifications.user.access']) {
          // message to user about last access system
          $notification = new Notification;
          $notification->user = $app->user;
          $notification->message = sprintf(\MapasCulturais\i::__("Seu último acesso foi em <b>%s</b>, atualize suas informações se necessário."),$app->user->lastLoginTimestamp->format('d/m/Y'));
          $notification->save();
        }
      }

      if(in_array('notifications',$app->config['plugins.enabled']) && $app->config['notifications.entities.update'] > 0) {
          $now = new \DateTime;
          foreach($this->agents as $agent) {
            $lastUpdateDate = $agent->updateTimestamp ? $agent->updateTimestamp: $agent->createTimestamp;
            $interval = date_diff($lastUpdateDate, $now);
            if($agent->status > 0 && !$agent->sentNotification && $interval->format('%a') >= $app->config['notifications.entities.update']) {
              // message to user about old agent registrations
              $notification = new Notification;
              $notification->user = $app->user;
              $notification->message = sprintf(\MapasCulturais\i::__("O agente <b>%s</b> não é atualizado desde de <b>%s</b>, atualize as informações se necessário. <a class='btn btn-small btn-primary' href='%s'>editar</a>'"),$agent->name,$lastUpdateDate->format("d/m/Y"),$agent->editUrl);
              $notification->save();

              // use the notification id to use it later on entity update
              $agent->sentNotification = $notification->id;
              $agent->save();
            }
          }

          foreach($this->spaces as $space) {
            $lastUpdateDate = $space->updateTimestamp ? $space->updateTimestamp: $space->createTimestamp;
            $interval = date_diff($lastUpdateDate, $now);

            if($space->status > 0 && !$space->sentNotification && $interval->format('%a') >= $app->config['notifications.entities.update']) {
              // message to user about old space registrations
              $notification = new Notification;
              $notification->user = $app->user;
              $notification->message = sprintf(\MapasCulturais\i::__("O Espaço <b>%s</b> não é atualizado desde de <b>%s</b>, atualize as informações se necessário. <a class='btn btn-small btn-primary' href='%s'>editar</a>"),$space->name,$lastUpdateDate->format("d/m/Y"),$space->editUrl);
              $notification->save();
              // use the notification id to use it later on entity update
              $space->sentNotification = $notification->id;
              $space->save();
            }
          }
        $app->em->flush();
      }

      if(in_array('notifications.seal.toExpire',$app->config) && $app->config['notifications.seal.toExpire'] > 0) {
          $diff = 0;
          $now = new \DateTime;
          foreach($this->agents as $agent) {
              foreach($agent->sealRelations as $relation) {
                if($relation->validateDate) {
                    $diff = ($relation->validateDate->format("U") - $now->format("U"))/86400;
                    if($diff <= 0.00) {
                        $notification = new Notification;
                        $notification->user = $app->user;
                        $notification->message = sprintf(\MapasCulturais\i::__("O Agente <b>%s</b> está com o seu selo <b>%s</b> expirado.<br>Acesse a entidade e solicite a renovação da validade. <a class='btn btn-small btn-primary' href='%s'>editar</a>"),$agent->name,$relation->seal->name,$agent->editUrl);
                        $notification->save();
                    } elseif($diff <= $app->config['notifications.seal.toExpire']) {
                        $diff = is_int($diff)? $diff: round($diff);
                        $diff = $diff == 0? $diff = 1: $diff;
                        $notification = new Notification;
                        $notification->user = $app->user;
                        $notification->message = sprintf(\MapasCulturais\i::__("O Agente <b>%s</b> está com o seu selo <b>%s</b> para expirar em %s dia(s).<br>Acesse a entidade e solicite a renovação da validade. <a class='btn btn-small btn-primary' href=''>editar</a>"),$agent->name,$relation->seal->name,((string)$diff),$agent->editUrl);
                        $notification->save();
                    }
                }
              }
          }

          foreach($this->spaces as $space) {
              foreach($space->sealRelations as $relation) {
                  if($relation->validateDate) {
                    $diff = ($relation->validateDate->format("U") - $now->format("U"))/86400;
                    if($diff <= 0.00) {
                        $notification = new Notification;
                        $notification->user = $app->user;
                        $notification->message = sprintf(\MapasCulturais\i::__("O Espaço <b>%s</b> está com o seu selo <b>%s</b> expirado.<br>Acesse a entidade e solicite a renovação da validade. <a class='btn btn-small btn-primary' href='%s'>editar</a>"),$space->name,$relation->seal->name,$space->editUrl);
                        $notification->save();
                    } elseif($diff <= $app->config['notifications.seal.toExpire']) {
                        $diff = is_int($diff)? $diff: round($diff);
                        $diff = $diff == 0? $diff = 1: $diff;
                        $notification = new Notification;
                        $notification->user = $app->user;
                        $notification->message = sprintf(\MapasCulturais\i::__("O Agente <b>%s</b> está com o seu selo <b>%s</b> para expirar em %s dia(s).<br>Acesse a entidade e solicite a renovação da validade. <a class='btn btn-small btn-primary' href='%s'>editar</a>"),$space->name,$relation->seal->name,((string)$diff),$space->editUrl);
                        $notification->save();
                    }
                  }
              }
          }
      }
    }

    //============================================================= //
    // The following lines ara used by MapasCulturais hook system.
    // Please do not change them.
    // ============================================================ //

    /** @ORM\PrePersist */
    public function prePersist($args = null){ parent::prePersist($args); }
    /** @ORM\PostPersist */
    public function postPersist($args = null){ parent::postPersist($args); }

    /** @ORM\PreRemove */
    public function preRemove($args = null){ parent::preRemove($args); }
    /** @ORM\PostRemove */
    public function postRemove($args = null){ parent::postRemove($args); }

    /** @ORM\PreUpdate */
    public function preUpdate($args = null){ parent::preUpdate($args); }
    /** @ORM\PostUpdate */
    public function postUpdate($args = null){ parent::postUpdate($args); }
}
