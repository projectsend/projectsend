<?php
/**
 * Class that generates a table element.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class generateTable {

	function __construct( $attributes ) {
		global $dbh;
		$this->dbh = $dbh;

		$this->contents = self::open( $attributes );
		$this->contents .= "<tbody>\n";

		$this->current_row = 1;
	}

	/**
	 * Create the form
	 */
	public function open( $attributes ) {
		$this->output = "<table";
		foreach ( $attributes as $tag => $value ) {
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
	 * the current sort order needs to be inversed on the link.
	 */
	private function build_sortable_th_content( $sort_url, $is_current_sorted, $content ) {
		$url_parse = parse_url( $_SERVER['REQUEST_URI'] );

		if ( !empty( $_GET ) ) {
			$new_url_parameters = $_GET;
		}
		$new_url_parameters['orderby'] = $sort_url;
		
		$order = 'desc';
		if ( !empty( $new_url_parameters['order'] ) ) {
			$order = ( $new_url_parameters['order'] == 'asc' ) ? 'desc' : 'asc';
			if ( $is_current_sorted != true ) {
				$order = ( $order == 'asc' ) ? 'desc' : 'asc';
			}
		}
		$new_url_parameters['order'] = $order;

		foreach ( $new_url_parameters as $param => $value ) {
			$params[$param] = $value;
		}
		$query = http_build_query($params);
		
		$build_url = BASE_URI . basename( $url_parse['path'] ) . '?' . $query;

		$sortable_link = '<a href="' . $build_url . '">';
		$sortable_link .= $content;
		$sortable_link .= '</a>';
		
		return $sortable_link;
	}

	public function thead( $columns ) {
		$this->output = "<thead>\n<tr>";
		if ( !empty( $columns ) ) {
			foreach ( $columns as $column ) {
				$attributes	= ( !empty( $column['attributes'] ) ) ? $column['attributes'] : array();
				$data_attr	= ( !empty( $column['data_attr'] ) ) ? $column['data_attr'] : array();
				$content	= ( !empty( $column['content'] ) ) ? $column['content'] : '';
				$sortable	= ( !empty( $column['sortable'] ) ) ? $column['sortable'] : false;
				$sort_url	= ( !empty( $column['sort_url'] ) ) ? $column['sort_url'] : false;
				$order		= ( !empty( $_GET['order'] ) ) ? html_output($_GET['order']) : 'desc';
				$is_current_sorted = false;

				if ( !empty( $column['hide'] ) ) {
					$data_attr['hide'] = $column['hide'];
				}
				if ( isset( $_GET['orderby'] ) && !empty( $sort_url ) ) {
					if ( $_GET['orderby'] == $sort_url ) {
						$attributes['class'][] = 'active';
						$attributes['class'][] = 'footable-sorted-' . $order;
						$is_current_sorted = true;
					}
				}
				else {
					if ( !empty( $column['sort_default'] ) ) {
						$attributes['class'][] = 'active';
						$attributes['class'][] = 'footable-sorted-' . $order;
						$sort_next = $order;
					}
				}

				if ( !empty( $column['select_all'] ) && $column['select_all'] === true ) {
					$content = '<input type="checkbox" name="select_all" id="select_all" value="0" />';
				}
				
				if ( $sortable == true && !empty( $sort_url ) ) {
					$content = self::build_sortable_th_content( $sort_url, $is_current_sorted, $content );
				}

				/**  Generate the column */
				$this->output .= '<th';
				foreach ( $attributes as $tag => $value ) {
					if ( is_array( $value ) ) {
						$value = implode(' ', $value);
					}
					$this->output .= ' ' . $tag . '="' . $value . '"';
				}
				foreach ( $data_attr as $tag => $value ) {
					$this->output .= ' data-' . $tag . '="' . $value . '"';
				}
				$this->output .= '>' . $content;
				
				if ( $sortable == true ) {
					$this->output .= '<span class="footable-sort-indicator"></span>';
				}

				$this->output .= '</th>';
			}
		}
		$this->output .= "</tr>\n</thead>\n";
		$this->contents .= $this->output;
	}
	
	public function tfoot( $columns ) {
		$this->output = "<tfoot>\n<tr>";
		if ( !empty( $columns ) ) {
			foreach ( $columns as $column ) {
				$attributes	= ( !empty( $column['attributes'] ) ) ? $column['attributes'] : array();
				$data_attr	= ( !empty( $column['data_attr'] ) ) ? $column['data_attr'] : array();
				$content	= ( !empty( $column['content'] ) ) ? $column['content'] : '';
				/**  Generate the column */
				$this->output .= '<td';
				foreach ( $attributes as $tag => $value ) {
					if ( is_array( $value ) ) {
						$value = implode(' ', $value);
					}
					$this->output .= ' ' . $tag . '="' . $value . '"';
				}
				foreach ( $data_attr as $tag => $value ) {
					$this->output .= ' data-' . $tag . '="' . $value . '"';
				}
				$this->output .= '>' . $content;
				
				$this->output .= '</td>';
			}
		}
		$this->output .= "</tr>\n</tfoot>\n";
		$this->contents .= $this->output;
	}
	
	public function add_row() {
		$this->row_class = ( $this->current_row % 2 ) ? 'table_row' : 'table_row_alt';
		$this->contents .= '<tr class="' . $this->row_class . '">' . "\n";
		$this->current_row++;
	}

	public function add_cell( $attributes ) {
		$this->content 		= ( !empty( $attributes['content'] ) ) ? $attributes['content'] : '';
		$this->is_checkbox 	= ( !empty( $attributes['checkbox'] ) ) ? true : false;
		$this->value 		= ( !empty( $attributes['value'] ) ) ? html_output( $attributes['value'] ) : null;
		
		if ( $this->is_checkbox == true ) {
			$this->content = '<input type="checkbox" name="batch[]" value="' . $this->value . '" />' . "\n";
		}

		$this->contents .= "<td>\n" . $this->content . "</td>\n";
	}

	public function end_row() {
		$this->contents .= "</tr>\n";
	}

	/**
	 * Print the full table
	 */
	public function render() {
		$this->contents .= "</tbody>\n</table>\n";
		return $this->contents;
	}

	/**
	 * PAGINATION
	 */
	private function construct_pagination_link( $link, $page = 1 ) {
		$params['page'] = $page;
	
		if ( !empty( $_GET ) ) {
			foreach ( $_GET as $param => $value ) {
				if ( $param != 'page' ) {
					$params[$param] = $value;
				}
			}
		}
		$this->query = http_build_query($params);
		
		return BASE_URI . $link . '?' . $this->query;
	}

	public function pagination( $params ) {
		$this->output = '';

		if ( $params['pages'] > 1 ) {
			$this->output = '<div class="container-fluid">
								<div class="row">
									<div class="col-xs-12 text-center">
										<nav aria-label="'. __('Results navigation','cftp_admin') . '">
											<div class="pagination_wrapper">
												<ul class="pagination">';

			/** First and previous */
			if ( $params['current'] > 1 ) {
				$this->output .= '<li>
										<a href="' . self::construct_pagination_link( $params['link'] ) .'" data-page="first"><span aria-hidden="true">&laquo;</span></a>
									</li>
									<li>
										<a href="'. self::construct_pagination_link( $params['link'], $params['current'] - 1 ) .'" data-page="prev">&lsaquo;</a>
									</li>';
			}

			/**
			 * Pages
			 *
			 * TODO: Skip pages that are too far away
			 * of the current one.
			 */
			$already_spaced = false;
			for ( $i = 1; $i <= $params['pages']; $i++ ) {
				if (
						( $i < $params['current'] - 3 || $i > $params['current'] + 3 ) &&
						( $i != 1 && $i != $params['pages'] )
					)
				{
					if ( $already_spaced == false ) {
						$this->output .= '<li class="disabled"><a href="#">...</a></li>';
						$already_spaced = true;
					}
					continue;
				}
				if ( $params['current'] == $i ) {
					$this->output .= '<li class="active"><a href="#">' . $i .'</a></li>';
				}
				else {
					$this->output .= '<li><a href="' . self::construct_pagination_link( $params['link'], $i) .'">' . $i .'</a></li>';
				}
				
				if ( $i > $params['current'] ) {
					$already_spaced = false;
				}
			}
			
			/** Next and last */
			if ( $params['current'] != $params['pages'] ) {
				$this->output .= '<li>
										<a href="' . self::construct_pagination_link( $params['link'], $params['current'] + 1 ) .'" data-page="next">&rsaquo;</a>
									</li>
									<li>
										<a href="' . self::construct_pagination_link( $params['link'], $params['pages'] ) .'" data-page="last"><span aria-hidden="true">&raquo;</span></a>
									</li>';
			}


			$this->output .= '				</ul>
									</div>
								</nav>';
			
			$this->output .= '<div class="form-group">
									<label class="control-label hidden-xs hidden-sm" for="page">' . __('Go to:','cftp_admin') . '</label>
									<input type="text" class="form-control" name="page" id="go_to_page" value="' . $params['current'] .'" />
								</div>
								<div class="form-group">
									<button type="submit" class="form-control"><span aria-hidden="true" class="glyphicon glyphicon-ok"></span></button>
								</div>';
			
			$this->output .= '		</div>
							</div>
						</div>';

		}
		
		return $this->output;
	}
}
?>