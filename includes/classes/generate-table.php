<?php
/**
 * Class that generates a table element.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class generateTable {

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}

	/**
	 * Create the form
	 */
	public function open( $attributes ) {
		$output = "<table";
		foreach ( $attributes as $tag => $value ) {
			$output .= ' ' . $tag . '="' . $value . '"';
		}
		$output .= ">\n";
		return $output;
	}

	public function add_thead( $columns ) {
		$output = "<thead>\n<tr>";
		if ( !empty( $columns ) ) {
			foreach ( $columns as $column ) {
				$attributes	= ( !empty( $column['attributes'] ) ) ? $column['attributes'] : array();
				$data_attr	= ( !empty( $column['data_attr'] ) ) ? $column['data_attr'] : array();
				$content	= ( !empty( $column['content'] ) ) ? $column['content'] : '';
				if ( !empty( $column['hide'] ) ) {
					$data_attr['hide'] = $column['hide'];
				}

				if ( isset( $_GET['sortby'] ) ) {
					if ( $_GET['sortby'] == $column['sort_url'] ) {
						$attributes['class'][] = 'active';
					}
				}
				else {
					if ( !empty( $column['sort_default'] ) ) {
						$attributes['class'][] = 'active';
					}
				}

				if ( !empty( $column['select_all'] ) && $column['select_all'] === true ) {
					$content = '<input type="checkbox" name="select_all" id="select_all" value="0" />';
				}

				/**  Generate the column */
				$output .= '<th';
				foreach ( $attributes as $tag => $value ) {
					if ( is_array( $value ) ) {
						$value = implode(' ', $value);
					}
					$output .= ' ' . $tag . '="' . $value . '"';
				}
				foreach ( $data_attr as $tag => $value ) {
					$output .= ' data-' . $tag . '="' . $value . '"';
				}
				$output .= '>' . $content . '</th>';
			}
		}
		$output .= "</tr>\n</thead>\n";
		return $output;
	}

	public function close() {
		$output = "</table>\n";
		return $output;
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
		$query = http_build_query($params);
		
		return BASE_URI . $link . '?' . $query;
	}

	public function pagination( $params ) {
		$output = '';

		if ( $params['pages'] > 1 ) {
			$output = '<nav aria-label="'. __('Results navigation','cftp_admin') . '">
						<div class="pagination_wrapper text-center">
							<ul class="pagination">';

			/** First and previous */
			if ( $params['current'] > 1 ) {
				$output .= '<li>
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
			for ( $i = 1; $i <= $params['pages']; $i++ ) {
				if ( $params['current'] == $i ) {
					$output .= '<li class="active"><a href="#">' . $i .'</a></li>';
				}
				else {
					$output .= '<li><a href="' . self::construct_pagination_link( $params['link'], $i) .'">' . $i .'</a></li>';
				}
			}
			
			/** Next and last */
			if ( $params['current'] != $params['pages'] ) {
				$output .= '<li>
								<a href="' . self::construct_pagination_link( $params['link'], $params['current'] + 1 ) .'" data-page="next">&rsaquo;</a>
							</li>
							<li>
								<a href="' . self::construct_pagination_link( $params['link'], $params['pages'] ) .'" data-page="last"><span aria-hidden="true">&raquo;</span></a>
							</li>';
			}

			$output .= '</ul>
						</div>
						</nav>';

		}
		
		return $output;
	}
}
?>