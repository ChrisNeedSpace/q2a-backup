<?php

class qa_html_theme_layer extends qa_html_theme_base {

function head_script()
{
	qa_html_theme_base::head_script();
	$this->output('<script type="text/javascript">'.
		'var bck_t = ""; function doCheck() { if(bck_t.length==0 || confirm(\'You are going to \'+bck_t+\'. \n\nProceed?\', \'Confirmation\')) return true; return false; }'.
	'</script>');
}
}

