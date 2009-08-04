<?php

class ControllerException extends Exception {}
class UnknownActionException extends ControllerException {}
class ExpectedControllerDefinitionException extends ControllerException {} 
