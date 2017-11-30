
if derivs only
    gen_derivs
elseif not export only
    correct_issn
    add_pdf_numberofpages
    gen_derivs
    gen_missing_ddid 
    insert pod data
    update full text link for digitized objects
 
if not derivs only
    if not withdrawn only
        export metadata
            get exportdefinition
            export_data
                select items
                     add_ubu_repository
                    get collection and metadata values
                write data to export file
    export withdrawn metadata  
        get exportdefinition
        export_data
            remove item from igistats
            write data to export file
    if not export only
        update_last_run_timestamp

if not export only and not derivs only
    report_pdfinfo_errors
    update igistats


other functions:
    add_jop_metadata (this field is used by some other products)
   

export metadata
    select items on the basis of the exportdef_id
    if no exportdef_id has been given, it's set to 0
    if exportdef_id==0: all exportdefs are used
    if an exportdef's fulldump is set to true, all items are exported
        else only the new and modified items are exported


derivatives
    get recently modified items for collections with derivatives=yes
    update the full-text url for these items
    put their handles in a string
    call the manifestation_store with that string as param


=================================
