Dit is een tijdelijke directory.
Hij wordt gebruikt voor een paar PHP scripts voor het verwerken van htm bestanden.
Na afloop van de conversie van digitale bijzondere collecties kunnen de scripts en de directory weg.
Voor vragen en opmerkingen: Marina

Uitleg van de scripts:
booksWithHtm.php bevat een array met alle boeken waarvoor fulltext (htm) beschikbaar is, gegroepeerd per 100 boeken.
FullTextPages.php is een simpele class die wat pagineringsgegevens uit de database haalt en parset.
Het feitelijke werk wordt gedaan door editfulltext.php. Per boek gaat deze door de betreffende htm directory heen, leest de files in, voegt er de extra meta tag met het nieuwe digitisation id toe en schrijft de file weg in de manifestation store. 
Omdat er in de manifestation store wordt geschreven, moet dit script worden uitgevoerd door user dspace.
