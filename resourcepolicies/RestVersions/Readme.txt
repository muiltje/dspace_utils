Volgorde van werken:

Voor retroconversie

repairReaders: set Restricted Access for all Readers

setPolicies: default_item_read voor alle collecties en anon read policies op alle items
    (dit wordt niet uitgevoerd op Readers)

addRetroBBPolicies: zet bundle en bitstream policies voor items die zijn gewijzigd tussen
    twee gegeven datums. doe bv alles van 1 jaar per keer.

retroEmbargo: maakt policies voor alle items die een embargo hebben.

removeForeverEmbargo: verwijdert de resource policies met embargo tot 2050-01-01
    het resultaat hiervan is dat die bundles en bitstreams geen anon read meer hebben

dedoublePolicies: controleert of er meerdere anon read permissies op een item, 
    bundle of bitstream staan.
    als er meerdere zijn en één daarvan is een embargo: verwijder alle andere
    als er meerdere zijn zonder embargo: behoudt de eerst gevondene en verwijder de rest

setEmbargoMetadata
    voor alle resources die anon read rechten met een startdatum na vandaag hebben:
    zet de accessrights op 'Embargoed Access'
    (omdat het retro is, zullen al deze items al een date.embargo hebben)

=====================

Dagelijkse nachtverwerking

Items die binnenkomen via Metis kunnen een embargo datum 2050-01-01 krijgen.
Andere embargo datums kunnen worden ingevuld bij de import uit andere bronnen
of handmatig door DV.

SCRIPTS

addResourcePolicies: voor alle items met last_modified gisteren en in_archive=t
    heeft dit item een date.embargo?
    zo ja, gezet op 2050: doe niets (voeg geen resource policy toe)
    zo ja, maar andere datum: voeg resource policy met startdatum toe
    zo nee, voeg resource policy zonder datum toe

addRightsMetadata: voor alle items met last_modified gisteren en in_archive=t
    is er een rights.accessrights veld
        zo nee, voeg het toe met Open Access (in de volgende stap kan dan nog gewijzigd worden)
    is er een embargo na vandaag
        zo ja, voor 2050: zet rights.accessrights op "Closed Access"
        zo ja, zet rights.accessrights veld op "Embargoed Access"

liftEmbargoMetadata: voor alle items met een embargo tot vandaag (dus items die vanaf vandaag leesbaar zijn)
    zet rights.accessrights metadata op "Open Access (free)"
    verwijder date.embargo veld (of laten we dat nog maar even staan?)
 
dedoublePolicies: controleert of er meerdere anon read permissies op een item, bundle of bitstream staan.
    als er meerdere zijn en één daarvan is een embargo: verwijder alle andere
    als er meerdere zijn zonder embargo: behoudt de eerst gevondene en verwijder de rest

cleanEmbargo: 
    verwijder alle resource policies met een startdatum van meer dan een week(?) geleden