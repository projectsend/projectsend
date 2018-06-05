<?php


namespace ProjectSend;


interface ControllerWrapperFactoryInterface
{
    public function resolve(string $containerTag, string $function): callable;
}