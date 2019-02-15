# Webform pre-populate

Pre-populate a Drupal Webform with an external data source without disclosing information in the URL.

Currently, only the _file_ data source is supported.

## Prepare a file

The file should be `csv` and have the following structure:

| hash                                                             | name          | email              |
|------------------------------------------------------------------|---------------|--------------------|
| d4735e3a265e16eee03f59718b9b5d03019c07d8b6c51f90da3a666eec13ab35 | Jim Morrisson | jim@thedoors.com   |
| 4e07408562bedb8b60ce05c1decfe3ad16b72230967de01f640b7e4729b49fce | Ray Manzarek  | ray@thedoors.com   |
| 4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a | Robby Krieger | robby@thedoors.com |
| ef2d127de37b942baad06145e54b0c619a1f22327b2ebbcfbec78f5564afe39d | John Densmore | john@thedoors.com  |

Where 
- `hash` is the relation with Drupal and the external system (e.g. Mailchimp)
- `name` and `email` are  keys of a Webform element

## Upload the file and configure the Webform

- Choose a Webform: _Structure > Webbforms_
- Select the _Settings_ tab then _Form_
- Under _Form behaviors > Prepopulate_ check _Allow all elements to be populated using query string parameters_
- Check then _Use a file to prepopulate_ and upload the file.

This action will store the file in the database and delete the file from the file system.
Prepopulate can be temporarily disabled by unchecking _Use a file to prepopulate_, data are still stored for a later use.

## Preview, test and delete prepopulate data.

On the _Build_ tab, select _Prepopulate_. 
