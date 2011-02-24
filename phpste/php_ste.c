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

static PHP_FUNCTION(phpste_iterator) {
	return;
}


