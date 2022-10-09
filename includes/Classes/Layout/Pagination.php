<?php
namespace ProjectSend\Classes\Layout;

class Pagination {
    private $template;

    public function __construct()
    {
        $this->template = '
        <div class="row">
            <div class="col-12">
                <div class="pagination_wrapper">
                    <div class="row">
                        <div class="col-12 col-md-6 text-center">
                            <nav aria-label="' . __('Results navigation', 'cftp_admin') . '">
                                <ul class="pagination">
                                    {pages}
                                </ul>
                            </nav>
                        </div>
                        <div class="col-12 col-md-6 text-end">
                            <div class="go_to_page">
                                <form method="get" action="{goto_action}" class="row row-cols-lg-auto g-3 align-items-center justify-content-end">
                                    <label class="control-label hidden-xs hidden-sm">' . __('Go to page:', 'cftp_admin') . '</label>
                                    <div class="col-12">
                                        <input type="text" class="form-control" name="page" id="page_number" value="{current_page}" />
                                        '.form_add_existing_parameters(['action']).'
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="form-control"><i aria-hidden="true" class="fa fa-check"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        ';
    }

    private function constructPaginationLink($link, $page = 1)
    {
        $params['page'] = $page;

        /**
         * List of parameters to ignore when building the pagination links.
         * TODO: change it so it ignores all but 'search' instead? must check
         * if there are other parameters that need to be saved.
         */
        $ignore_current_params = array(
            'page',
            'categories_actions',
            'action',
            'do_action',
            'batch',
        );

        if (!empty($_GET)) {
            foreach ($_GET as $param => $value) {
                if (!in_array($param, $ignore_current_params)) {
                    $params[$param] = $value;
                }
            }
        }
        $this->query = http_build_query($params);

        return BASE_URI . $link . '?' . $this->query;
    }

    public function make($parameters)
    {
        if (!is_numeric($parameters['current'])) {
            $parameters['current'] = 1;
        } else {
            $parameters['current'] = (int)$parameters['current'];
        }

        $layout = '';

        $pages = 0;
        $items_per_page = get_option('pagination_results_per_page');
        if (!empty($parameters['items_per_page']) && is_numeric($parameters['items_per_page'])) {
            $items_per_page = $parameters['items_per_page'];
        }
        if (!empty($parameters['item_count']) && is_numeric($parameters['item_count'])) {
            $pages = ceil($parameters['item_count'] / $items_per_page);
        }

        if ($pages > 1) {
            $layout_pages = '';
            // First and previous 
            if ($parameters['current'] > 1) {
                $layout_pages .= '<li class="page-item">
										<a class="page-link" href="' . $this->constructPaginationLink($parameters['link']) . '" data-page="first"><span aria-hidden="true">&laquo;</span></a>
									</li>
									<li class="page-item">
										<a class="page-link" href="' . $this->constructPaginationLink($parameters['link'], $parameters['current'] - 1) . '" data-page="prev">&lsaquo;</a>
									</li>';
            }

            /** Pages */
            $already_spaced = false;
            for ($i = 1; $i <= $pages; $i++) {
                if (
                    ($i < $parameters['current'] - 3 || $i > $parameters['current'] + 3) &&
                    ($i != 1 && $i != $pages)
                ) {
                    if ($already_spaced == false) {
                        $layout_pages .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                        $already_spaced = true;
                    }
                    continue;
                }
                if ($parameters['current'] == $i) {
                    $layout_pages .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                } else {
                    $layout_pages .= '<li class="page-item"><a class="page-link" href="' . $this->constructPaginationLink($parameters['link'], $i) . '">' . $i . '</a></li>';
                }

                if ($i > $parameters['current']) {
                    $already_spaced = false;
                }
            }

            /** Next and last */
            if ($parameters['current'] != $pages) {
                $layout_pages .= '<li class="page-item">
										<a class="page-link" href="' . $this->constructPaginationLink($parameters['link'], $parameters['current'] + 1) . '" data-page="next">&rsaquo;</a>
									</li>
									<li class="page-item">
										<a class="page-link" href="' . $this->constructPaginationLink($parameters['link'], $pages) . '" data-page="last"><span aria-hidden="true">&raquo;</span></a>
									</li>';
            }

            $goto_action = $this->removePageUrlParam($this->constructPaginationLink($parameters['link']));

            $layout = str_replace(
                ['{pages}', '{goto_action}', '{current_page}'],
                [$layout_pages, $goto_action, $parameters['current']],
                $this->template
            );
        }

        return $layout;
    }

    private function removePageUrlParam($url)
    {
        $url = preg_replace('/(&|\?)'.preg_quote('page').'=[^&]*$/', '', $url);
        $url = preg_replace('/(&|\?)'.preg_quote('page').'=[^&]*&/', '$1', $url);
        $url = str_replace(BASE_URI, '', $url);

        return $url;
    }
}