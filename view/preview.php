<div class="postbox rw-body">
    <h3>Preview</h3>
    <div class="inside" style="padding: 10px;">
        <div id="rw_preview_container" style="text-align: <?php
            if ($rw_options->advanced->layout->align->ver != "middle")
            {
                echo "center";
            }
            else
            {
                if ($rw_options->advanced->layout->align->hor == "right"){
                    echo "left";
                }else{
                    echo "right";
                }
            }
        ?>;">
            <div id="rw_preview_star" class="rw-ui-container rw-urid-3"></div>
            <div id="rw_preview_nero" class="rw-ui-container rw-ui-nero rw-urid-17"></div>
        </div>
        <div class="rw-js-container">
            <script type="text/javascript">
                // Append RW JS lib.
                if (typeof(RW) == "undefined"){ 
                    (function(){
                        var rw = document.createElement("script"); rw.type = "text/javascript"; rw.async = true;
                        rw.src = "http://<?php echo $this->rw_domain; ?>/js/external.php";
                        var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(rw, s);
                    })();
                }
                
                var rwStar, rwNero;
                
                // Initialize ratings.
                function RW_Async_Init(){
                    RW.init("cfcd208495d565ef66e7dff9f98764da", <?php echo $rw_options_str; ?>);
                    RW.render(function(ratings){
                        rwStar = RWM.STAR = ratings[3];
                        rwNero = RWM.NERO = ratings[17];
                        
                        <?php
                            if ($rw_options->type == "star"){
                                echo 'jQuery("#rw_preview_nero").hide();';
                            }else{
                                echo 'jQuery("#rw_preview_star").hide();';
                            }
                        ?>
                    }, false);
                }
            </script>
        </div>
    </div>
</div>
