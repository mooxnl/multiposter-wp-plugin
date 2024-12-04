<?php
// Include WordPress header
get_header(); ?>
<div class="container jobit-container">
  <div class="row">
   
    <div class="vacancies__wrapper" style="min-height: 2087px;">
                
    
        <div class="pull-left col-xs-12 col-sm-4 col-lg-3">
            <div class="aside">
                <div class="vacancies__filter" style="margin-top: 0px; z-index: 999; top: -65px;">
                    <div class="container">
                    <div class="col-xs-12 col-md-10 col-md-offset-1">
                        <div class="row">
                        <div class="col-xs-12">
                            <div class="vacancies__filter__wrapper">
                            <form class="filter form-count auto-form-vacancies replace-state" id="jobs-filter" action="vacatures" method="get">
                                <input name="action" type="hidden" value="filter">
                                <div class="inputs">
                                <div class="form-group type-keyword">
                                    <span class="anchor anchor--inputfield">Zoeken</span>
                                    <input type="text" name="keyword" class="jobtitle form-control selected-filter fontsize-variant" placeholder="Zoeken..." value="">
                                </div>

                                <div class="form-group type-keyword">
                                    <span class="anchor anchor--inputfield">Functie</span>
                                    <?php
                                        $terms = get_terms(array(
                                            'taxonomy' => 'position',
                                            'hide_empty' => true, // Set to true to hide empty terms
                                        ));
                                        echo '<ul class="select_position">';
                                        foreach ($terms as $term) {
                                            echo '<li>';
                                                echo '<input type="checkbox" class="selected_position" name="selected_position[]"   value="'.$term->term_id.'" id="city'.$term->term_id.'"><label for="city'.$term->term_id.'">'.$term->name.'</label>';
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    ?>
                                </div>


                                <div class="form-group type-keyword">
                                    <span class="anchor anchor--inputfield">Plaats</span>
                                    <?php
                                        $terms = get_terms(array(
                                            'taxonomy' => 'cities',
                                            'hide_empty' => true, // Set to true to hide empty terms
                                        ));
                                        echo '<ul class="select_city">';
                                        foreach ($terms as $term) {
                                            echo '<li>';
                                                echo '<input type="checkbox" class="selected_city" name="selected_city[]"   value="'.$term->term_id.'" id="city'.$term->term_id.'"><label for="city'.$term->term_id.'">'.$term->name.'</label>';
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    ?>
                                </div>





                                </div>
                                
                            </form>
                            </div>
                            <div id="jobs-filters-used"></div>
                        </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="pull-right col-xs-12 col-sm-8 col-lg-9">
            <div id="jobs-results">

                <div class="perpage">
                    <select>
                        <option value="10" selected>10 per pagina</option>
                        <option value="20">25 per pagina</option>
                        <option value="50">50 per pagina</option>
                        <option value="100">100 per pagina</option>
                    </select>
                </div>


                <div class="loader__wrapper">
                    <div class="loader">
                    <i class="fa fa-spin fa-spinner"></i>
                    </div>
                </div>



                <div class="vacancies"></div>
                <div class="pagination"></div>
                
                    
                </div>
                
        </div>





    </div>

  </div>
</div>

<?php
// Include WordPress footer
get_footer();