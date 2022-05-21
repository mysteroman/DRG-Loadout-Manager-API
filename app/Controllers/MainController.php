<?php namespace Controllers;

use Models\Brokers\DataBroker;
use Models\Brokers\LoadoutBroker;
use Models\Brokers\UserBroker;
use phpDocumentor\Reflection\Types\Integer;
use Zephyrus\Application\Configuration;
use Zephyrus\Application\Rule;
use Zephyrus\Utilities\ComposerPackage;
use Zephyrus\Network\ContentType;

class MainController extends Controller
{
    /**
     * Defines all the routes supported by this controller associated with inner methods. The first argument is a string
     * representation of the uri of the route you want to define starting with a slash. The second argument is the name
     * of a public method accessible within this class to process the route call. It is possible to include parameters
     * within the route uri definition using curly braces (E.g /item/{id}).
     */
    public function initializeRoutes()
    {
        // User
		$this->post("/user", "signup", ContentType::FORM);
		$this->get("/user", "login", ContentType::FORM);
        // $this->post("/user/follow", "follow");
		// $this->get("/user/follow", "getFollows");
		
		// Versions
        $this->get("/versions", "getVersions");

		// Data
		$this->get("/dwarves", "getDwarves");
		$this->get('/dwarf', 'getDwarf', ContentType::FORM);
        $this->get("/perks", "getPerks");
		
		// Loadouts
		$this->get("/loadouts", "getLoadouts", ContentType::FORM);
		$this->get("/loadout", "getLoadout", ContentType::FORM);
		$this->post("/loadout", "createLoadout", ContentType::FORM);
		// $this->patch("/loadout", "updateLoadout", ContentType::FORM);
		// $this->delete("/loadout", "deleteLoadout", ContentType::FORM);
    }

    public function signup(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        $form->field('username')->validate(Rule::notEmpty("You must enter a username"));
        $form->field('email')->validate(Rule::email("You must enter a valid email"));
        $form->field('password')->validate(Rule::passwordCompliant("Please enter a valid password: It must contain at least eight characters, of which there are at least one uppercase letter, one lowercase letter and one number"));

        if (!$form->verify())
        {
            $errors = $form->getErrors();
            return $this->json($errors);
        }

        $info = $form->buildObject();
        $broker = new UserBroker();
        if ($broker->tryCreateUser($info->username, $info->email, $info->password))
        {
            return $this->json(null);
        }
        return $this->json(["Couldn't create the account"]);
    }

    public function login(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        $form->field('username')->validate(Rule::notEmpty());
        $form->field('password')->validate(Rule::passwordCompliant());

        if (!$form->verify()) return $this->json(null);

        $info = $form->buildObject();
        $broker = new UserBroker();
        return $this->json($broker->authUser($info->username, $info->password));
    }

    public function follow(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        $form->field('key')->validate(Rule::notEmpty());
        $form->field('user')->validate(Rule::notEmpty());
        $form->field('value')->validate(Rule::boolean());

        if (!$form->verify()) return $this->json(false);

        $info = $form->buildObject();
        $broker = new UserBroker();

        return $this->json(false);
    }

    public function getFollows(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        $form->field('key')->validate(Rule::notEmpty());
        $form->field('user')->setOptionalOnEmpty(true);

        if (!$form->verify()) return $this->json(null);

        $info = $form->buildObject();
        $key = $info->key;
        $user = $info->user;
        $broker = new UserBroker();

        if (!is_null($user))
        {
            return $this->json($broker->doesUserFollow($key, $user));
        }

        return $this->json($broker->getUserFollows($key));
    }

    public function getVersions(): \Zephyrus\Network\Response
    {
        $broker = new DataBroker();
        return $this->json($broker->findAllVersions());
    }

    public function getDwarves(): \Zephyrus\Network\Response
    {
        $broker = new DataBroker();
        return $this->json($broker->findAllDwarves());
    }

    public function getDwarf(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        $form->field('id')->validate(Rule::integer());

        if (!$form->verify()) return $this->json(null);

        $info = $form->buildObject();

        $broker = new DataBroker();
        return $this->json($broker->findDwarfById($info->id));
    }

    public function getPerks(): \Zephyrus\Network\Response
    {
        $broker = new DataBroker();
        return $this->json($broker->findAllPerks());
    }

    public function getLoadouts(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        // $form->field('viewer')->validate(Rule::integer(), true);
        $form->field('page')->validate(Rule::integer(), true);
        $form->field('count')->validate([Rule::integer(), Rule::range(1, 100)], true);
        $form->field('sort')->validate(Rule::inArray(['date', 'asc_date', 'name', 'asc_name']), true);
        // $form->field('name')->validate(Rule::notEmpty(), true);
        // $form->field('dwarf')->validate(Rule::integer(), true);
        // $form->field('primary')->validate(Rule::integer(), true);
        // $form->field('secondary')->validate(Rule::integer(), true);
        // $form->field('perks')->validate([Rule::array(), Rule::all(Rule::integer())], true);

        if (!$form->verify()) return $this->json(null);

        $request = $form->buildObject();
        $request->page ??= 0;
        $request->count ??= 20;
        $request->sort ??= 'date';

        $broker = new LoadoutBroker();
        return $this->json($broker->findAllFromRequest($request));
    }

    public function getLoadout(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        $form->field('id')->validate(Rule::integer());

        if (!$form->verify()) return $this->json(null);

        $info = $form->buildObject();

        $broker = new LoadoutBroker();
        return $this->json($broker->findById($info->id));
    }

    public function createLoadout(): \Zephyrus\Network\Response
    {
        $form = $this->buildForm();
        $form->field('key')->validate(Rule::notEmpty());
        $form->field('loadout')->validate(Rule::json());

        if (!$form->verify()) return $this->json(false);

        $info = $form->buildObject();

        $broker = new UserBroker();
        $user = $broker->getUserIdFromKey($info->key);
        if (is_null($user)) return $this->json(false);

        $broker = new LoadoutBroker();
        return $this->json($broker->addLoadout($user, $info->loadout));
    }
}
