                </div> <!-- container-fluid -->

                <?php render_footer_text(); ?>
            </div> <!-- container-fluid -->
        </main>
        <?php
            render_json_variables();
            
            render_assets('js', 'footer');
            render_assets('css', 'footer');

            render_custom_assets('body_bottom');
        ?>
    </body>
</html>
<?php
    if ( DEBUG === true ) {
        // echo "\n" . '<!-- DEBUG INFORMATION' . "\n";
        // echo "\n" . '-->' . "\n" ;
    }

    ob_end_flush();
