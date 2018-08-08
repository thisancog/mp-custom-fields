

# MP Custom Fields

A Wordpress plugin to offer easy, performant, flexible and robust custom fields management with UI in mind.
The motivation behind this project was to create an alternative to popular custom fields plugins which too often sacrifice speed and ease of use for a maximum range of customisability.

This plugin offers a simple interface to register new custom fields from within the code, while a GUI interface is currently in the making.

## Usage

There is no extensive documentation yet, however, most of the code should be easily understandable anyway. A simple example to register a new meta box with several custom fields could look like this:

    // an array of fields organised in panels
    $panelMedia = array(
	    'name'		=> 'Media',
		'icon'		=> 'dashicons-images-alt',
		'fields'	=> array(
			array(
				'name'	=> 'thumbnail2',
				'title'	=> 'Additional featured image',
				'type'	=> 'media'
			),
			array(
				'name'	=> 'thumbcrop',
				'title'	=> 'Crop',
				'type'	=> 'buttongroup',
				'options'	=> array(
					'none'		=> 'none',
					'top'		=> 'top',
					'bottom'	=> 'bottom'
				),
				'default'		=> 'none',
				'description'	=>
					'If and how to crop this image.'
			),
			array(
				'name'	=> 'thumbcaption',
				'title'	=> 'Caption',
				'type'	=> 'text',
				'placeholder'	=> 'Caption...'
			)
		)
    );

    // some general information about the meta box
    $box = array(
	    'context'	=> 'normal',
	    'priority'	=> 'high',
	    'title'		=> 'A meta box title',
	    'panels'	=> array($panelmedia, $anotherPanel)
    );
	
	$type = 'post';	// the post type to use this meta box for
	$id = 'metabox-posts';	// a unique id for this meta box
	
    // arguments:
    // $type (string): post type
    // $id (string): unique identifier for this meta box
    // $box (array): holds all the information for
    // the metabox and its fields
    
    mpcf_add_custom_fields($type,  $id, $box);

… which produces this result:
![The meta box created by the above code](https://github.com/thisancog/mp-custom-fields/blob/master/docs/exampleScreenshot.png)

## Supported field types
As of now, the following custom field types are already built-in. MP Custom Field is modularised which means, in order to register a new one, create a new file in the `inc/modules/` directory and define a new class, extending on the `MPCFModule` class. Find examples how to register a new field type as well as information on available settings there.

### Text inputs
- WYSIWYG Editor (register with title `editor`)
- Email (`email`)
- Password (`password`)
- Text (`text`)
- Textarea (`textarea`)

### Date and time
 - Date (`date`)
 - Date and time (`datetime`)
 - Month (`month`)
 - Time (`time`)
 - Week (`week`)
 
### Choices
 - Button group (`buttongroup`)
 - Checkbox (`checkbox`)
 - Radio button group (`radio`)
 - Select (`select`)
 - True/false (`truefalse`)

### Numbers
 - Number (`number`)
 - Range (`range`)

### Miscellaneous
 - Color picker (`color')
 - Conditional field: defines a dropdown list and swaps further fields based on the choice made (`conditional`)
 - File upload (`file`)
 - Hidden field (`hidden`)
 - Map: requires a Google Maps API, see backend settings page (`map`)
 - Media: images and videos (`media`)
 - Repeater: includes a repeatable set of fields (`repeater`)
