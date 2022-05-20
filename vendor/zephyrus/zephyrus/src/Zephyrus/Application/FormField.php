<?php namespace Zephyrus\Application;

use stdClass;

class FormField
{
    /**
     * Holds the field name as registered when the form has been submitted. E.g. if an array has been submitted with the
     * name "selections[]", this name would be registered.
     *
     * @var string
     */
    private string $name;

    /**
     * Holds the given field value when the form has been submitted.
     *
     * @var mixed
     */
    private mixed $value;

    /**
     * Internal list of all rules assigned to the field in programmatic order. Meaning the order the addRule method is
     * called is important as the rules will be executed in such order.
     *
     * @var stdClass[]
     */
    private array $rules = [];

    /**
     * Determines if the form field has an error. Will change during the verification of the rules if at least one of
     * them fails. Cannot rely exclusively on the error messages list because the rules could have empty messages.
     *
     * @var bool
     */
    private bool $hasError = false;

    /**
     * Associative array for all the registered error of the field with the key being the error pathing. Default pathing
     * is the field name.
     *
     * @var array
     */
    private array $errors = [];

    /**
     * Determines if the error keys should contain the full pathing for nested rules. If the field has nested errors the
     * pathing could be "students.2.name" which means students[2]['name'] has the error.
     *
     * @var bool
     */
    private bool $useNestedPathing = true;

    /**
     * Dictates how to handle a field with an empty value with an optional rule. By default, an optional rule on an
     * empty value is considered optional. Can be changed if for some reason a specific field is not considered optional
     * when empty.
     *
     * @var bool
     */
    private bool $optionalOnEmpty = true;

    /**
     * Dictates if the rule verification should stop when an error is encountered or if all validation should be
     * executed nonetheless. By default, the validation process ends when an error is encountered because traditionally
     * all rules affected to a field are somewhat dependant (e.g. ->validate(Rule::notEmpty())->validate(Rule::name()))
     * and thus would make the resulting errors redondant.
     *
     * @var bool
     */
    private bool $verifyAll = false;

    /**
     * Class constructor to initialize a field with its name and value.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __construct(string $name, mixed $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * Registers a rule or a group of rules to be applied to the field. The optional argument allows the rule to be
     * skipped if the value is undefined or empty (depending on the optionalOnEmpty). Rules are verified only when the
     * verify method is called.
     *
     * @param Rule|array $rule
     * @param bool $optional
     * @return FormField
     */
    public function validate(Rule|array $rule, bool $optional = false): FormField
    {
        if (is_array($rule)) {
            foreach ($rule as $item) {
                $this->addRule($item, $optional);
            }
        } else {
            $this->addRule($rule, $optional);
        }
        return $this;
    }

    /**
     * Instead of stopping at the first failed rule validation, it will proceed to validate all given rules and
     * accumulate errors. Useful to retrieve all errors on a field at once. Default behavior is to stop and return the
     * first encountered error on a field.
     */
    public function all()
    {
        $this->verifyAll = true;
    }

    /**
     * Retrieves the given value of the field.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Retrieves the given name of the field.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the complete error information including the pathing. Returns an associative array where the key is
     * the pathing and the value is an array of messages.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retrieves the list of registered errors.
     *
     * @return string[]
     */
    public function getErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $errors) {
            foreach ($errors as $error) {
                $messages[] = $error;
            }
        }
        return $messages;
    }

    /**
     * Verify the affected validation rules on the field. The $fields argument should contain all the form data
     * available because some rules may need to access other form fields. Returns true is all validation have passed or
     * false otherwise. If it returns false, the errors can be read with getErrorMessages().
     *
     * @param array $fields
     * @return bool
     */
    public function verify(array $fields = []): bool
    {
        foreach ($this->rules as $validation) {
            if ($this->isRuleTriggered($validation) && !$validation->rule->isValid($this->value, $fields)) {
                $pathing = "";
                if ($this->useNestedPathing) {
                    $pathing = $validation->rule->getPathing();
                    if (!empty($pathing)) {
                        $pathing = '.' . $pathing;
                    }
                }
                $this->errors[$this->name . $pathing][] = $validation->rule->getErrorMessage();
                $this->hasError = true;
                if (!$this->verifyAll) {
                    return false;
                }
            }
        }
        return !$this->hasError;
    }

    /**
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->hasError;
    }

    /**
     * @return bool
     */
    public function isOptionalOnEmpty(): bool
    {
        return $this->optionalOnEmpty;
    }

    /**
     * @param bool $emptyIsOptional
     */
    public function setOptionalOnEmpty(bool $emptyIsOptional)
    {
        $this->optionalOnEmpty = $emptyIsOptional;
    }

    public function isUsingNestedPathing(): bool
    {
        return $this->useNestedPathing;
    }

    public function setUseNestedPathing(bool $useNestedPathing)
    {
        $this->useNestedPathing = $useNestedPathing;
    }

    private function isRuleTriggered(stdClass $validation): bool
    {
        if (!$validation->optional) {
            return true;
        }
        if (is_null($this->value)
            || ($this->optionalOnEmpty && empty($this->value))) {
            return false;
        }
        return true;
    }

    private function addRule(Rule $rule, bool $optional)
    {
        $validation = new stdClass();
        $validation->rule = $rule;
        $validation->optional = $optional;
        $this->rules[] = $validation;
    }
}
