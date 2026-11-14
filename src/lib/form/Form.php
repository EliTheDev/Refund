<?php

declare(strict_types = 1);

namespace lib\form;

use pocketmine\form\Form as IForm;
use pocketmine\player\Player;
use X\module\types\player\class\User;

abstract class Form implements IForm{

    /** @var array */
    protected $data = [];
    /** @var callable|null */
    private $callable;

    /**
     * @param callable|null $callable
     */
    public function __construct(?callable $callable) {
        $this->callable = $callable;
    }

    /**
     * @deprecated
     * @see User::sendForm()
     *
     * @param User $user
     */
    public function sendToUser(User $user) : void {
        $user->sendForm($this);
    }

    public function getCallable() : ?callable {
        return $this->callable;
    }

    public function setCallable(?callable $callable) {
        $this->callable = $callable;
    }

    public function handleResponse(Player $player, $data) : void {
        $this->processData($data);
        $callable = $this->getCallable();
        if($callable !== null) {
            $callable($player, $data);
        }
    }

    public function processData(&$data) : void {
    }

    public function jsonSerialize(): mixed{
        return $this->data;
    }
}
