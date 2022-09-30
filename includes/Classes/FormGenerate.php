<?php
/**
 * Class that generates a form and its fields.
 */

namespace ProjectSend\Classes;

class FormGenerate
{
    private $open;
    public $new_password_fields;

    function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;

        $this->close = "</form>\n";
        $this->output = '';
        $this->contents = '';

        $this->group_class = 'form-group row';
        $this->checkbox_group_class = 'checkbox';
        $this->label_class = 'col-sm-4 control-label';
        $this->wrap_class = 'col-sm-8';
        $this->wrap_group = 'input-group';
        $this->checkbox_wrap = 'col-sm-8 col-sm-offset-4';
        $this->field_class = 'form-control';

        $this->new_password_fields = [];

        $this->ignore_field_class = array(
            'hidden',
            'checkbox',
            'radio',
            'separator',
        );

        $this->ignore_layout = array(
            'hidden',
            'separator',
        );
    }

    /**
     * Create the form
     */
    public function create($arguments)
    {
        $this->open .= $this->generateElementTag('form', false, false, false, $arguments);
    }

    /**
     * Generate each tag
     * form, input, textarea, etc
     */
    private function generateElementTag($element, $close_tag, $type, $add_type, $arguments)
    {
        $this->attributes = !(empty($arguments['attributes'])) ? $arguments['attributes'] : null;
        $this->value = !(empty($arguments['value'])) ? $arguments['value'] : null;
        $this->content = !(empty($arguments['content'])) ? $arguments['content'] : null;
        $this->options = !(empty($arguments['options'])) ? $arguments['options'] : null;
        $this->check_var = !(empty($arguments['check_var'])) ? $arguments['check_var'] : null;
        $this->selected = !(empty($arguments['selected'])) ? $arguments['selected'] : null;
        $this->required = !(empty($arguments['required'])) ? true : false;
        $this->label = !(empty($arguments['label'])) ? $arguments['label'] : null;

        if ($type == 'password') {
            $this->attributes['class'][] = 'attach_password_toggler';
        }

        $this->properties = [];
        $this->result = '';

        if ($element != 'form') {
            $this->result .= "\t";
        }

        $this->result .= '<' . $element . ' ';

        if ($add_type == true) {
            $this->properties['type'] = $type;
        }

        foreach ($this->attributes as $tag => $val) {
            if (empty($val)) {
                $this->properties[$tag] = '';
            } else {
                $this->properties[$tag] = $val;
            }
        }

        // If ID is not defined, use the name attr to add it
        if (!empty($this->attributes['name']) && empty($this->attributes['id'])) {
            $this->properties['id'] = $this->attributes['name'];
        }

        if ($this->required == true) {
            $this->properties['required'] = '';
        }

        if (!empty($this->check_var)) {
            if ($this->check_var == $arguments['value']) {
                $this->properties['checked'] = 'checked';
            }
        }

        if (!empty($this->value)) {
            $this->properties['value'] = $this->value;
        }

        $this->produce = [];
        foreach ($this->properties as $property => $val) {
            if (!empty($val)) {
                $this->produce[] = $property . '="' . $val . '"';
            } else {
                $this->produce[] = $property;
            }
        }

        // Add each attribute to the tag
        $this->result .= implode(' ', $this->produce);

        // Close the opening tag
        $this->result .= '>' . "\n";

        // Used on textarea
        if (!empty($this->content)) {
            $this->result .= $this->content;
        }

        // Used on select
        if (!empty($this->options)) {
            foreach ($this->options as $val => $name) {
                $this->result .= $this->generateOption($val, $name, $this->selected);
            }
        }

        // Does the element need closing tag? (textarea, select...)
        if ($close_tag == true) {
            $this->result .= '</' . $type . '>' . "\n";
        }

        return $this->result;
    }

    /**
     * Generate the options for a select field
     */
    private function generateOption($value, $name, $selected)
    {
        $this->option_properties = [];

        $this->option = "\t\t\t" . '<option ';
        $this->option_properties[] = 'value="' . $value . '"';
        if (!empty($selected) && $selected == $value) {
            $this->option_properties[] = 'selected="selected"';
        }
        // Add the properties
        $this->option .= implode(' ', $this->option_properties);

        $this->option .= '>' . $name;
        $this->option .= '</option>' . "\n";

        return $this->option;
    }

    /**
     * Generate a simple separator
     */
    private function generateSeparator()
    {
        $this->option = "\n" . '<div class="separator"></div>' . "\n\n";
        return $this->option;
    }

    /**
     * This button goes under the password field and generates
     * a new random password. The $field_name param is the input
     * that the result will be applied to.
     */
    private function generatePasswordButton($field_name)
    {
        $this->button_arguments = array(
            'type' => 'button',
            'content' => 'Generate',
            'attributes' => array(
                'name' => 'generate_password',
                'class' => 'btn btn-light btn-sm btn_generate_password',
                'data-ref' => $field_name,
                'data-min' => MIN_PASS_CHARS,
                'data-max' => MAX_PASS_CHARS,
            )
        );
        $this->button = $this->generateElementTag('button', true, $this->button_arguments['type'], true, $this->button_arguments);
        $this->new_password_fields[] = $this->button_arguments['attributes']['name'];

        return $this->button;
    }

    public function field($type, $arguments)
    {
        // Set default to avoid repetition
        $this->label_location = 'outside';
        $this->use_layout = (!in_array($type, $this->ignore_layout)) ? true : false;

        if (!empty($arguments['required']) && $arguments['required'] == true) {
            $arguments['attributes']['class'][] = 'required';
        }

        if (!empty($arguments['label'])) {
            $this->label = '<label>' . $arguments['label'] . '</label>' . "\n";
        }

        // Try to add the default field class
        if (!in_array($type, $this->ignore_field_class)) {
            if (empty($arguments['default_class']) || $arguments['default_class'] == true) {
                $arguments['attributes']['class'][] = $this->field_class;
            }
        }

        // Concat the classes
        if (!empty($arguments['attributes']['class'])) {
            $arguments['attributes']['class'] = implode(' ', $arguments['attributes']['class']);
        }

        switch ($type) {
            case 'text':
            default:
                $this->field = $this->generateElementTag('input', false, $type, true, $arguments);
                break;
            case 'password':
                $this->field = $this->generateElementTag('input', false, $type, true, $arguments);
                break;
            case 'hidden':
                $this->field = $this->generateElementTag('input', false, $type, true, $arguments);
                break;
            case 'textarea':
                $this->field = $this->generateElementTag('textarea', true, $type, false, $arguments);
                break;
            case 'select':
                $this->field = $this->generateElementTag('select', true, $type, false, $arguments);
                break;
            case 'checkbox':
            case 'radio':
                $this->label_location = 'wrap';
                $this->field = $this->generateElementTag('input', false, $type, true, $arguments);
                break;
            case 'button':
                $this->field = $this->generateElementTag('button', true, $type, false, $arguments);
                break;
            case 'separator':
                $this->field = $this->generateSeparator();
                break;
        }

        // Format according to the Bootstrap 3 layout
        if ($this->use_layout == true) {
            $this->layout = '<div class="' . $this->group_class . '">' . "\n";
            switch ($this->label_location) {
                case 'outside':
                    $this->format = "\t" . '<label for="%s" class="%s">%s</label>' . "\n";
                    $this->layout .= sprintf($this->format, $arguments['attributes']['name'], $this->label_class, $arguments['label']);
                    $this->layout .= "\t" . '<div class="' . $this->wrap_class . '">' . "\n";

                    if ($type == 'password') {
                        $this->layout .= "\t\t" . '<div class="' . $this->wrap_group . '">' . "\n";
                        $this->layout .= "\t\t" . $this->field;
                        if (function_exists('password_notes')) {
                            $this->layout .= password_notes();
                        }
                        $this->layout .= "\t\t" . '</div>' . "\n";

                        if (!empty($arguments['pass_type']) && $arguments['pass_type'] == 'create') {
                            $this->layout .= $this->generatePasswordButton($arguments['attributes']['name']);
                        }
                    } else {
                        $this->layout .= "\t" . $this->field;
                    }

                    $this->layout .= "\t" . '</div>' . "\n";
                    break;
                case 'wrap':
                    $this->layout .= "\t" . '<div class="' . $this->checkbox_wrap . '">' . "\n";
                    $this->layout .= "\t\t" . '<div class="' . $type . '">' . "\n";
                    $this->layout .= "\t\t\t" . '<label for="' . $arguments['attributes']['name'] . '">' . "\n";
                    $this->layout .= "\t\t\t" . $this->field;
                    $this->layout .= "\t\t\t\t" . ' ' . $arguments['label'] . "\n";
                    $this->layout .= "\t\t\t" . '</label>' . "\n";
                    $this->layout .= "\t\t" . '</div>' . "\n";
                    $this->layout .= "\t" . '</div>' . "\n";
                    break;
            }
            $this->layout .= "</div>\n";
        } else {
            $this->layout = $this->field;
        }

        $this->contents .= $this->layout;
    }

    public function output()
    {
        $this->output = $this->open . $this->contents . $this->close;
        return $this->output;
    }
}
