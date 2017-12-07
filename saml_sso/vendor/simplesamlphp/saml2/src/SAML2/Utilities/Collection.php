<?php

interface SAML2_Utilities_Collection extends ArrayAccess, Countable, IteratorAggregate
{
    /**
     * Add an element to the collection
     *
     * @param $element
     *
     * @return $this|SAML2_Utilities_Collection
     */
    public function add($element);

    /**
     * Shorthand for getting a single element that also must be the only element in the collection.
     *
     * @return mixed
     *
     * @throws SAML2_Exception_RuntimeException if the element was not the only element
     */
    public function getOnlyElement();

    /**
     * Return the first element from the collection
     *
     * @return mixed
     */
    public function first();

    /**
     * Return the last element from the collection
     *
     * @return mixed
     */
    public function last();

    /**
     * Applies the given function to each element in the collection and returns a new collection with the elements returned by the function.
     *
     * @param callable $function
     *
     * @return mixed
     */
    public function map(Closure $function);

    /**
     * @param callable $filterFunction
     *
     * @return SAML2_Utilities_Collection
     */
    public function filter(Closure $filterFunction);

    /**
     * Get the element at index
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function get($key);

    /**
     * @param $element
     */
    public function remove($element);

    /**
     * Set the value for index
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function set($key, $value);
}
