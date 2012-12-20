<?php
namespace ERD\AnnotationHelpers;

/**
 * Offers the infrastructure for creating annotations that extend other annotations higher up the in the
 * class hierarchy.
 *
 * This class doesn't store each field in a property, because, if we did that, then we couldn't easily
 * distinguish between a property that was explicitly set to null in the annotation and one that was
 * simply not set in the annotation (they'd both have the value null in the object). So we use an
 * internal array with __get/set/isset/unset instead, and a property that wasn't set simply won't exist
 * as a key on the array, while one set null explicitly would exist as a key but with the value null.
 * (We're faking a bit of JS-like dynamism.)
 *
 * @Annotation
 */
class InheritableAnnotation
{
    /**
     * @var array All the data on this annotation, in the form propName => value.
     */
    protected $data = array();

    /**
     * @var array All the allowed keys in this annotation.
     */
    protected static $allowedProperties = array('for');

    public function __construct(array $data)
    {
        foreach($data as $key=>$value)
        {
            if(in_array($key, static::$allowedProperties))
            {
                $this->data[$key] = $value;
            }
        }
    }

    /**
     * Give subclasses an easy way to add a bunch of allowed properties at once
     */
    protected function addAllowedProperties(array $propNames)
    {
        static::$allowedProperties = array_merge(static::$allowedProperties, $propNames);
    }
    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if(array_key_exists($name, $this->data))
        {
            return $this->data[$name];
        }
    }

    public function __set($name, $value)
    {
        if(in_array($name, static::$allowedProperties))
        {
            $this->data[$name] = $value;
        }
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->data);
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    public function getCurrentProperties()
    {
        return array_keys($this->data);
    }

    /**
     * @param self|array $from
     * @param bool $overwrite Overwrite existing values in this annotation when there's a conflict?
     */
    public function mergeIn($from, $overwrite=true)
    {
        $isArray = is_array($from);
        $props = (!$isArray) ? $from->getCurrentProperties() : array_keys($from);

        foreach($props as $prop)
        {
            $fromVal = ($isArray) ? $from[$prop] : $from->{$prop};

            //if the property doesn't exist in $to, we need to add it, regardless of overwrite
            //if it does exist and its not an array (so no opportunity to merge $to and $from)
            //we decide whether to overwrite its value depending solely on $overwrite.
            if(!isset($this->{$prop}) || (!is_array($this->{$prop}) && $overwrite))
            {
                $this->{$prop} = $fromVal;
            }

            //if the property exists in $to but it's an array, we merge the values, but we use
            //$overwrite to decide which value wins if there's a key conflict.
            elseif(is_array($this->{$prop}))
            {
                if($overwrite)
                {
                    $this->{$prop} = array_merge($this->{$prop}, $fromVal);
                }
                else
                {
                    $this->{$prop} = array_merge($fromVal, $this->{$prop});
                }
            }
        }
    }
}
