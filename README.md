### Enhance reason for change ###

The Enhance reason for change module provides extra configuration for the built-in reason for change process (available
when the option to 'Require a reason when making changes to existing records?' option is checked). 

This module inserts code into the `DataEntry\index.php` REDCap file when the module is enabled at a system
level. The code is removed when the module is disabled.

#### Set up and configuration

Enabling the module at a system level will AUTOMATICALLY insert code into the `DataEntry\index.php` file to provide
a dropdown of system level reasons for change. Optionally it will also change the character limit of the reasons for 
change by amending the code limiting the character input length.
These changes are triggered by the system hook `redcap_module_system_enable`.

The inserted code is surrounded by a comment so should be easily found;
```html
<!-- ****** inserted by Enhance Reasons for Change module ****** -->
[mark up here]
<!-- ****** end of insert ****** -->
```

Disabling the module at a system level will AUTOMATICALLY remove the inserted code and revert the character input
length using the system hook `redcap_module_system_disable`.

When a new version of the module becomes available, the module should be disabled and then re-enabled from the Control Center at the system level. Failure to do so may cause the module to malfunction.

##### System level configuration

- `enlarge-reason-text-capacity` - changes the default maximum characters allowed in the 'reason for change' text entry
  field from 200 to 5000
- `sys-reason-for-change-option` - a list of options that are used to populate the dropdown control configurable at a
  system level. The options provided are always present in the dropdown for any project in the system and are always
  the first options available. See project configuration below for how these can be appended to.

##### Project level configuration

- `provide-reasons-for-change-dropdown` - when checked, a dropdown control is provided with a selection of reasons that 
  users can select. This negates the need to write free text each time. The free text option remains
- `reason-for-change-option` - a list of options that are used to populate the dropdown control configurable at a 
  project level. These are relevant only if the `provide-standard-reasons-dropdown` option is checked. The options 
  provided are simply appended to the system level options given by `sys-reason-for-change-option`
- `highlight-field-when-changed` - when checked, any changes made to a field either directly, or via the 'mark field'
  mechanism or the date time picker highlight the field's row's right border. Note that the mechanism mimics REDCap's
  'dirty field' implementation in that a change to a field permanently marks the field as 'changed' even if the user
  reverts the value back to its original value. For example, changing the value 10 to 20 then back to 10 will still
  leave the field highlighted as changed
- `highlight-field-when-changed-style` - the style to apply when highlighting the field. The style applied should be
  appropriate to the right border of the row e.g. 'dashed 3px blue'. The default is 'solid 2px red'. If the given style
  is invalid, the highlighting will not show.

#### Features

When the 'Reason for change' feature is enabled, users may wish to enter more than 200 characters when populating the
text area element. Whilst this is excessive in some cases, users may wish to include all details of every field change
in one entry, and therefore possibly exceed the default limitation of 200 characters. The underlying data field is of 
type 'text' which permits an effectively unlimited number of characters, however the field is limited by a javascript 
function to 200 chars. This limit can be upped to 5000 using this module by checking the appropriate option.

The reason for change currently accepts free text. This module doesn't change that, though it allows admin users to
provide a series of standard responses which can be used to quickly populate the free text from a dropdown. An initial
set of standard reasons *applicable to all projects* can be set using the system configuration. Alternatively these
options can be left blank. At a project level, users can optionally enter further reasons for change that will be 
appended to the system level ones. You cannot alter system level options at a project level, nor change the order in 
which they are presented to the user. Only include system options that should be consistent in text and order for
every project using the Data Resolution Workflow.

Finally, any changes to fields are not really visible to users. This module enables highlighting of any changed fields
as the changes occur. If changed and highlighted, a field will remain like that regardless of whether the user resets
the value back to its original value. This behaviour is also how REDCap handles changes and notifications to users 
should they try and navigate away from a 'dirty' form.


#### Future work

Whilst a useful feature, the built-in reason for change feature is a bit of a blunt tool. Providing a free text field
for recording change reasons at a form level may be sufficient for many cases, but should the requirement be to record
changes at a field level, the system provides scant support. Users would need to be highly motivated and change a field
at a time then save their change with the relevant details before changing the next field. For anything but the smallest
form, it is impossible for users to provide details against each field change in an efficient way. Whilst there is some 
debate about the usefulness of this improvement, it should be considered a priority item when developing this module 
further.