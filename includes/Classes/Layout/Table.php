<?php
/**
 * Class that generates a table element.
 */

namespace ProjectSend\Classes\Layout;

class Table
{
    function __construct($attributes)
    {
        $this->contents = $this->open($attributes);

        $this->current_row = 1;
    }

    /**
     * Create the form
     */
    public function open($attributes)
    {
        $this->output = "<table";
        foreach ($attributes as $tag => $value) {
            $this->output .= ' ' . $tag . '="' . $value . '"';
        }
        $this->output .= ">\n";
        return $this->output;
    }

    /**
     * If a column name is sortable, it's content has to be a link
     * to the current page + existing $_GET parameters, but adding
     * the orderby and order ones.
     * If "order" is set and the current column in the loop contains
     * the current sort order needs to be inverted on the link.
     */
    private function buildSortableThContent($sort_url, $is_current_sorted, $content)
    {
        $url_parse = parse_url($_SERVER['REQUEST_URI']);
        $url_parts = explode('/', $url_parse['path']);
        array_shift($url_parts);

        if (!empty($_GET)) {
            $new_url_parameters = $_GET;
        }
        $new_url_parameters['orderby'] = $sort_url;

        $order = 'desc';
        if (!empty($new_url_parameters['order'])) {
            $order = ($new_url_parameters['order'] == 'asc') ? 'desc' : 'asc';
            if ($is_current_sorted != true) {
                $order = ($order == 'asc') ? 'desc' : 'asc';
            }
        }
        $new_url_parameters['order'] = $order;

        foreach ($new_url_parameters as $param => $value) {
            /**
             * Page is not added, so when you click a table header
             * pagination always returns to page 1.
             */
            if ($param != 'page') {
                $params[$param] = $value;
            }
        }
        $query = http_build_query($params);

        $build_url = BASE_URI . implode('/',$url_parts) . '?' . $query;

        $sortable_link = '<a href="' . $build_url . '">';
        $sortable_link .= $content;
        $sortable_link .= '</a>';

        return $sortable_link;
    }

    public function thead($columns)
    {
        $this->output = "<thead>\n<tr>";
        if (!empty($columns)) {
            foreach ($columns as $column) {
                $continue = (!isset($column['condition']) || !empty($column['condition'])) ? true : false;
                if ($continue == true) {

                    $attributes = (!empty($column['attributes'])) ? $column['attributes'] : array();
                    $data_attr = (!empty($column['data_attr'])) ? $column['data_attr'] : array();
                    $content = (!empty($column['content'])) ? $column['content'] : '';
                    $sortable = (!empty($column['sortable'])) ? $column['sortable'] : false;
                    $sort_url = (!empty($column['sort_url'])) ? $column['sort_url'] : false;
                    $order = (!empty($_GET['order'])) ? html_output($_GET['order']) : 'desc';

                    $is_current_sorted = false;

                    if (!empty($column['hide'])) {
                        $data_attr['hide'] = $column['hide'];
                    }
                    if (isset($_GET['orderby']) && !empty($sort_url)) {
                        if ($_GET['orderby'] == $sort_url) {
                            $attributes['class'][] = 'active';
                            $attributes['class'][] = 'footable-sorted-' . $order;
                            $is_current_sorted = true;
                        }
                    } else {
                        if (!empty($column['sort_default'])) {
                            $attributes['class'][] = 'active';
                            $attributes['class'][] = 'footable-sorted-' . $order;
                            $sort_next = $order;
                        }
                    }

                    if (!empty($column['select_all']) && $column['select_all'] === true) {
                        $content = '<input type="checkbox" name="select_all" id="select_all" value="0" />';
                    }

                    if ($sortable == true && !empty($sort_url)) {
                        $content = $this->buildSortableThContent($sort_url, $is_current_sorted, $content);
                    } else {
                        $data_attr['sort-ignore'] = 'true';
                    }

                    // Generate the column
                    $this->output .= '<th';
                    foreach ($attributes as $tag => $value) {
                        if (is_array($value)) {
                            $value = implode(' ', $value);
                        }
                        $this->output .= ' ' . $tag . '="' . $value . '"';
                    }
                    foreach ($data_attr as $tag => $value) {
                        $this->output .= ' data-' . $tag . '="' . $value . '"';
                    }
                    $this->output .= '>' . $content;

                    if ($sortable == true) {
                        $this->output .= '<span class="footable-sort-indicator"></span>';
                    }

                    $this->output .= '</th>';
                }
            }
        }
        $this->output .= "</tr>\n</thead>\n";
        $this->contents .= $this->output;
    }

    public function tfoot($columns)
    {
        $this->output = "<tfoot>\n<tr>";
        if (!empty($columns)) {
            foreach ($columns as $column) {
                $attributes = (!empty($column['attributes'])) ? $column['attributes'] : array();
                $data_attr = (!empty($column['data_attr'])) ? $column['data_attr'] : array();
                $content = (!empty($column['content'])) ? $column['content'] : '';
                // Generate the column
                $this->output .= '<td';
                foreach ($attributes as $tag => $value) {
                    if (is_array($value)) {
                        $value = implode(' ', $value);
                    }
                    $this->output .= ' ' . $tag . '="' . $value . '"';
                }
                foreach ($data_attr as $tag => $value) {
                    $this->output .= ' data-' . $tag . '="' . $value . '"';
                }
                $this->output .= '>' . $content;

                $this->output .= '</td>';
            }
        }
        $this->output .= "</tr>\n</tfoot>\n";
        $this->contents .= $this->output;
    }

    public function addRow()
    {
        if ($this->current_row == 1) {
            $this->contents .= "<tbody>\n";
        }

        $this->row_class = ($this->current_row % 2) ? 'table_row' : 'table_row_alt';
        $this->contents .= '<tr class="' . $this->row_class . '">' . "\n";
        $this->current_row++;
    }

    public function addCell($attributes)
    {
        $continue = (!isset($attributes['condition']) || !empty($attributes['condition'])) ? true : false;

        if ($continue == true) {
            $this->attributes = (!empty($attributes['attributes'])) ? $attributes['attributes'] : array();
            $this->content = (!empty($attributes['content'])) ? $attributes['content'] : '';
            $this->is_checkbox = (!empty($attributes['checkbox'])) ? true : false;
            $this->value = (!empty($attributes['value'])) ? html_output($attributes['value']) : null;

            if ($this->is_checkbox == true) {
                $this->content = '<input type="checkbox" class="batch_checkbox" name="batch[]" value="' . $this->value . '" />' . "\n";
            }

            $this->contents .= "<td";
            if (!empty($this->attributes)) {
                foreach ($this->attributes as $tag => $value) {
                    if (is_array($value)) {
                        $value = implode(' ', $value);
                    }
                    $this->contents .= ' ' . $tag . '="' . $value . '"';
                }
            }
            $this->contents .= ">\n" . $this->content . "</td>\n";
        }
    }

    public function end_row()
    {
        $this->contents .= "</tr>\n";
    }

    /**
     * Print the full table
     */
    public function render()
    {
        $this->contents .= "</tbody>\n</table>\n";
        return $this->contents;
    }
}
