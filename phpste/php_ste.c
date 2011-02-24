#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"

extern zend_module_entry phpste_module_entry;
#define phpext_ctype_ptr &phpste_module_entry

#include "SAPI.h"
#include "ext/standard/info.h"

#include <ctype.h>

static PHP_MINFO_FUNCTION(phpste);

static PHP_FUNCTION(phpste_iterator);

ZEND_BEGIN_ARG_INFO(arginfo_phpste_iterator, 0)
	ZEND_ARG_INFO(0, v)
ZEND_END_ARG_INFO()

static const zend_function_entry phpste_functions[] = {
	PHP_FE(phpste_iterator,	arginfo_phpste_iterator)
	{NULL, NULL, NULL}
};

zend_module_entry phpste_module_entry = {
	STANDARD_MODULE_HEADER,
	"phpste",
	phpste_functions,
	NULL,
	NULL,
	NULL,
	NULL,
	PHP_MINFO(phpste),
    NO_VERSION_YET,
	STANDARD_MODULE_PROPERTIES
};

ZEND_GET_MODULE(phpste)

static PHP_MINFO_FUNCTION(phpste)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "phpste functions", "enabled");
	php_info_print_table_end();
}

/*
// Grabbed: function twig_ensure_traversable($seq)
function phpste_iterator($seq)
{
    if (is_array($seq) || (is_object($seq) && $seq instanceof Traversable)) {
        return $seq;
    } else {
        return array();
    }
}
*/

static PHP_FUNCTION(phpste_iterator) {
	zval *v;
	
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &v) == FAILURE) goto return_empty_array;

	if (Z_TYPE_P(v) == IS_ARRAY) {
		goto return_direct;
	}
	
	if (Z_TYPE_P(v) == IS_OBJECT) {
		zend_class_entry **ce;
		if (zend_lookup_class_ex("Traversable", 11, NULL, 0, &ce TSRMLS_CC) == FAILURE) goto return_empty_array;

		if (instanceof_function(Z_OBJCE_P(v), *ce TSRMLS_CC)) {
			goto return_direct;
		}
	}

return_empty_array:
	array_init(v);
	//RETURN_NULL();

return_direct:
	RETURN_ZVAL(v, 1, 0);
	//RETURN_NULL();
}


