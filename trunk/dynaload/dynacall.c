/*
cl /O2 /MD dynacall.c
tcc -run dynacall.c
*/

#include <stdio.h>
#include <stdint.h>
#include <windows.h>

#ifdef x64
	#error Not implemented yet
#endif

#define ZEND_DEBUG 0

#ifdef ZTS
#define USING_ZTS 1
#else
#define USING_ZTS 0
#endif

typedef unsigned int zend_uint;
typedef unsigned int uint;
typedef unsigned long long ulong;

typedef unsigned char zend_bool;
typedef unsigned char zend_uchar;
typedef unsigned int zend_uint;
typedef unsigned long zend_ulong;
typedef unsigned short zend_ushort;

typedef struct _zend_module_entry zend_module_entry;
typedef struct _zval_struct zval;
typedef struct _zend_class_entry zend_class_entry;
typedef unsigned int zend_object_handle;
typedef struct _zend_object_handlers zend_object_handlers;

struct _hashtable;

typedef struct bucket {
	ulong h;						/* Used for numeric indexing */
	uint nKeyLength;
	void *pData;
	void *pDataPtr;
	struct bucket *pListNext;
	struct bucket *pListLast;
	struct bucket *pNext;
	struct bucket *pLast;
	char arKey[1]; /* Must be last element */
} Bucket;

typedef struct _hashtable {
	uint nTableSize;
	uint nTableMask;
	uint nNumOfElements;
	ulong nNextFreeElement;
	Bucket *pInternalPointer;	/* Used for element traversal */
	Bucket *pListHead;
	Bucket *pListTail;
	Bucket **arBuckets;
	void* pDestructor;
	zend_bool persistent;
	unsigned char nApplyCount;
	zend_bool bApplyProtection;
#if ZEND_DEBUG
	int inconsistent;
#endif
} HashTable;

typedef struct _zend_function_entry {
	const char *fname;
	void (*handler)(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used, void ***tsrm_ls);
	const struct _zend_arg_info *arg_info;
	zend_uint num_args;
	zend_uint flags;
} zend_function_entry;

struct _zend_module_entry {
	unsigned short size;
	unsigned int zend_api;
	unsigned char zend_debug;
	unsigned char zts;
	const struct _zend_ini_entry *ini_entry;
	const struct _zend_module_dep *deps;
	const char *name;
	const struct _zend_function_entry *functions;
	int (*module_startup_func  )(int type, int module_number, void ***tsrm_ls);
	int (*module_shutdown_func )(int type, int module_number, void ***tsrm_ls);
	int (*request_startup_func )(int type, int module_number, void ***tsrm_ls);
	int (*request_shutdown_func)(int type, int module_number, void ***tsrm_ls);
	void (*info_func)(zend_module_entry *zend_module, void ***tsrm_ls);
	const char *version;
	size_t globals_size;
#ifdef ZTS
	ts_rsrc_id* globals_id_ptr;
#else
	void* globals_ptr;
#endif
	void (*globals_ctor)(void *global, void ***tsrm_ls);
	void (*globals_dtor)(void *global, void ***tsrm_ls);
	int (*post_deactivate_func)(void);
	int module_started;
	unsigned char type;
	void *handle;
	int module_number;
	char *build_id;
};

typedef struct _zend_guard {
	zend_bool in_get;
	zend_bool in_set;
	zend_bool in_unset;
	zend_bool in_isset;
	zend_bool dummy; /* sizeof(zend_guard) must not be equal to sizeof(void*) */
} zend_guard;

typedef struct _zend_object {
	zend_class_entry *ce;
	HashTable *properties;
	HashTable *guards; /* protects from __get/__set ... recursion */
} zend_object;

struct _zend_object_handlers {
	/* general object functions */
	void* add_ref;
	void* del_ref;
	void* clone_obj;
	/* individual object functions */
	void* read_property;
	void* write_property;
	void* read_dimension;
	void* write_dimension;
	void* get_property_ptr_ptr;
	void* get;
	void* set;
	void* has_property;
	void* unset_property;
	void* has_dimension;
	void* unset_dimension;
	void* get_properties;
	void* get_method;
	void* call_method;
	void* get_constructor;
	void* get_class_entry;
	void* get_class_name;
	void* compare_objects;
	void* cast_object;
	void* count_elements;
	void* get_debug_info;
	void* get_closure;
};

typedef struct _zend_object_value {
	zend_object_handle handle;
	zend_object_handlers *handlers;
} zend_object_value;

typedef union _zvalue_value {
	long lval;					/* long value */
	double dval;				/* double value */
	struct {
		char *val;
		int len;
	} str;
	HashTable *ht;				/* hash table value */
	zend_object_value obj;
} zvalue_value;

struct _zval_struct {
	/* Variable information */
	zvalue_value value;		/* value */
	zend_uint refcount__gc;
	zend_uchar type;	/* active type */
	zend_uchar is_ref__gc;
};

extern void php_info_print_table_start();
extern void php_info_print_table_header(int, ...);
extern void php_info_print_table_row(int, ...);
extern void php_info_print_table_row_ex(int, const char *, ...);
extern void php_info_print_table_end();

extern void zend_register_long_constant(const char *name, unsigned int name_len, long lval, int flags, int module_number, void ***tsrm_ls);
extern int  zend_register_functions(zend_class_entry *scope, const zend_function_entry *functions, HashTable *function_table, int type, void ***tsrm_ls);
extern int _zend_get_parameters_array_ex(int param_count, zval ***argument_array, void ***tsrm_ls);
extern void convert_to_long(zval *op);
extern void _convert_to_string(zval *op, char*, int);

/*
int WINAPI MessageBox(
  __in_opt  HWND hWnd,
  __in_opt  LPCTSTR lpText,
  __in_opt  LPCTSTR lpCaption,
  __in      UINT uType
);
*/

// function, function_pointer
#define __CallBuffer_push_value_increment(type, value) { *(type *)(function_pointer) = (type)(value); function_pointer += sizeof(type); }

// MOV EAX, value
// PUSH EAX
#define CallBuffer_push_32(value) { \
	__CallBuffer_push_value_increment(uint8_t, 0xB8); \
	__CallBuffer_push_value_increment(uint32_t, (value)); \
	__CallBuffer_push_value_increment(uint8_t, 0x50); \
}

// MOV EAX, value
// CALL EAX
#define CallBuffer_call(func) { \
	\
	__CallBuffer_push_value_increment(uint8_t, 0xB8); \
	__CallBuffer_push_value_increment(uint32_t, (func)); \
	\
	__CallBuffer_push_value_increment(uint8_t, 0xFF); \
	__CallBuffer_push_value_increment(uint8_t, 0xD0); \
}

#define CallBuffer_ret() __CallBuffer_push_value_increment(uint8_t, 0xC3);

#define CallBuffer_execute() ((void (*)(void))function)();
#define CallBuffer_reset() function_pointer = function;

#define CallBuffer_call_ret_execute(func) { \
	CallBuffer_call(func); \
	CallBuffer_ret(); \
	CallBuffer_execute(); \
}

void main_test() {
	HANDLE lib = LoadLibraryA("User32.dll");
	void* func = GetProcAddress(lib, "MessageBoxA");

	char *function = (char *)VirtualAlloc(NULL, 1024, MEM_COMMIT | MEM_RESERVE, PAGE_EXECUTE_READWRITE);
	//char *function = (char*)malloc(1024);
	char *function_pointer;

	printf("%08X\n", function);
	
	if (function == NULL) {
		fprintf(stderr, "Memory not reserved.\n");
		return -1;
	}
	
	// MessageBoxA(0, "Hello", "World", 0);
	CallBuffer_reset();
	{
		CallBuffer_push_32(0);
		CallBuffer_push_32("Hello");
		CallBuffer_push_32("World");
		CallBuffer_push_32(0);
	}
	CallBuffer_call_ret_execute(func);
	
	printf("%08X, %08X\n", lib, func);
	return 0;
}

const zend_function_entry module_functions[] = {
	{NULL, NULL, NULL, 0, 0},
	{NULL, NULL, NULL, 0, 0}
};

#define convert_to_string(zval) _convert_to_string((zval), __FILE__, __LINE__)

void test(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used, void ***tsrm_ls) {
	zval** args[4] = {0};
	int result;
	result = _zend_get_parameters_array_ex(4, args, tsrm_ls);
	if (result == -1) {
		fprintf(stderr, "Invalid parameters");
		return;
	}

	char *function = (char *)VirtualAlloc(NULL, 1024, MEM_COMMIT | MEM_RESERVE, PAGE_EXECUTE_READWRITE);
	char *function_pointer;

	convert_to_long(*args[0]);
	convert_to_string(*args[1]);
	convert_to_string(*args[2]);
	convert_to_long(*args[3]);

	CallBuffer_reset();
	{
		CallBuffer_push_32((*args[0])->value.lval);
		CallBuffer_push_32((*args[1])->value.str.val);
		CallBuffer_push_32((*args[2])->value.str.val);
		CallBuffer_push_32((*args[3])->value.lval);
	}
	CallBuffer_call_ret_execute(MessageBoxA);

	return;
}

int module_startup_func(int type, int module_number, void ***tsrm_ls) {
	//zend_register_long_constant("test", 5, 99, 0, 0, tsrm_ls);
	
	module_functions[0].fname = "test";
	module_functions[0].handler = test;
	module_functions[0].arg_info = NULL;
	module_functions[0].num_args = 0;
	module_functions[0].flags = 0;
	
	zend_register_functions(NULL, module_functions, NULL, 0, tsrm_ls);

	printf("module_startup_func\n");
	return 0;
}

int module_shutdown_func(int type, int module_number, void ***tsrm_ls) {
	printf("module_shutdown_func\n");
	return 0;
}

int request_startup_func(int type, int module_number, void ***tsrm_ls) {
	printf("request_startup_func\n");
	return 0;
}

int request_shutdown_func(int type, int module_number, void ***tsrm_ls) {
	printf("request_shutdown_func\n");
	return 0;
}

void info_func(zend_module_entry *zend_module, void ***tsrm_ls) {
	printf("info_func\n");
}

zend_module_entry module_module_entry = {
	sizeof(zend_module_entry), 20090626, ZEND_DEBUG, USING_ZTS, NULL, NULL,
	"dynacall",
	module_functions,
	module_startup_func,
	module_shutdown_func,
	request_startup_func,
	request_shutdown_func,
	info_func,
	NULL,
	0, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, "API20090626,TS,VC9"
};

__declspec(dllexport) zend_module_entry* get_module() {
	//main_test(); printf("dynacall.get_module()");
	return &module_module_entry;
}

/*int DllMain(HINSTANCE hInstance, DWORD fdwReason, LPVOID lpvReserved) {
	//printf("Hello!\n");
	if (fdwReason) DisableThreadLibraryCalls(hInstance);
	return 0;
}*/

/*int main(char** argv, int argc) {
	main_test();
}*/
