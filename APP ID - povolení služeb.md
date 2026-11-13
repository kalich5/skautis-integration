Seznam toho, jaké služby je potřeba povolit ve SkautISu. https://test-is.skaut.cz/JunakWebservice/

U každé další verze bude napsané, jen to co potřebuje navíc oproti předchozí verzi.

Verze 1.0
<br>
<b>OrganizationUnit -> PersonDetail</b> - Načíst detail osoby
<br>
<b>OrganizationUnit -> UnitAll</b> - Načíst seznam jednotek
<br>
<b>OrganizationUnit -> MembershipAllPerson</b> - Načíst seznam členství osoby
<br>
<b>OrganizationUnit -> UnitDetail</b> - Načíst detail jednotky
<br>
<b>OrganizationUnit -> MembershipTypeAll</b> - Načíst seznam typů členství 
<br>
<b>UserManagement -> LoginUpdate</b> - Přihlášení uživatele na jinou roli
<br>
<b>UserManagement -> RoleDetail</b> - Načíst detail role
<br>
<b>UserManagement -> UserRoleAll</b> - Načíst seznam rolí přiřazených uživateli
<br>
<b>UserManagement -> UserAll</b> - Načíst seznam uživatelů
<br>
<b>UserManagement -> UserDetail</b> - Načíst detail uživatele
<br>
<b>UserManagement -> RoleAll</b> - Načíst seznam rolí

Verze 1.1
<br>
<b>OrganizationUnit -> FunctionTypeAll</b> - Načíst seznam typů funkcí
<br>
<b>OrganizationUnit -> FunctionAllPerson</b> - Načíst seznam funkcí osoby

Verze 1.1.4
<br>
<b>Events -> EventAllPerson</b> - Načíst seznam akcí osoby
<br>
<b>Events -> EventDetail</b> - Načíst detail akce 
<br>
<b>Events -> ParticipantGeneralAll</b> - Načíst seznam účastníků obecné akce
<br>
<b>Events -> ParticipantCampAll</b> - Načíst seznam účastníků tábora 
<br>
<b>Events -> ParticipantEducationAll</b> - Načíst seznam účastníků vzdělávací akce
<br>
<b>Events -> ParticipantAllPerson</b> - Načíst seznam osob, které zúčastní sněmu

Verze 1.1.12
<br>
<b>OrganizationUnit -> QualificationTypeAll</b> - Načíst seznam typů kvalifikace
<br>
<b>OrganizationUnit -> QualificationAll</b> - Načíst seznam kvalifikací osoby

Verze 1.2.0
<br>
<b>Events -> InstructorAll</b> - Načíst seznam instruktorů vzdělávací akce (akce ve SkautISu: EV_Instructor_ALL_EventEducation)
<br>
<b>Events -> EventFunctionAllEducationLeader</b> - Načíst vedení vzdělávací akce (EV_EventFunction_ALL_EventEducationLeader)
<br>
<b>Events -> EventFunctionAllCamp</b> - Načíst vedení tábora (EV_EventFunction_ALL_EventCamp)
<br>
<b>Events -> EventFunctionAllGeneral</b> - Načíst vedení obecné akce (EV_EventFunction_ALL_EventGeneral)
<br>
Tyto čtyři služby používá Správa uživatelů: když je uživatel přepnutý na roli v akci, vypíše kromě účastníků i instruktory a vedení akce. Bez nich se vypíšou jen účastníci a zobrazí se upozornění.
<br>
Pravidlo „Akce“ používá jen služby z předchozích verzí (Events -> EventAllPerson, UserManagement -> UserRoleAll).

<b>Nábor a mapa oddílů (bloky „Nábor“ a „Mapa oddílů“)</b>
<br>
<b>OrganizationUnit -> UnitAll</b> - Načíst seznam jednotek (už ve verzi 1.0; jednotka podle registračního čísla a její oddíly)
<br>
<b>OrganizationUnit -> AdvertisingDetail</b> - Načíst detail náboru jednotky (poznámka, vodní oddíl)
<br>
<b>OrganizationUnit -> AdvertisingCategoryAll</b> - Načíst náborové kategorie (věk a pohlaví)
<br>
<b>OrganizationUnit -> MeetingDateAll</b> - Načíst termíny schůzek
<br>
<b>OrganizationUnit -> OccupationAll</b> - Načíst klubovny jednotky (adresa a GPS pro mapu)
<br>
Tyto služby se volají jen s App ID webu, bez přihlášeného uživatele, protože nábor vidí i návštěvníci webu. Data se načtou nejvýš jednou denně (a po uložení nastavení na stránce SkautIS → Nábory).

<b>Informace o jednotce (blok „Jednotka“)</b>
<br>
<b>OrganizationUnit -> UnitAll</b> a <b>UnitDetail</b> - už z verze 1.0 (jednotka podle registračního čísla, sídlo, IČ, podřízené jednotky)
<br>
<b>OrganizationUnit -> UnitContactAll</b> - Načíst kontakty jednotky (jen ty označené jako veřejné)
<br>
<b>OrganizationUnit -> FunctionAllRegistry</b> - Načíst statutární orgán, zástupce a kontaktní osobu z rejstříku
<br>
<b>OrganizationUnit -> AccountAll</b> - Načíst bankovní účty jednotky (jen když blok zobrazuje bankovní účet)
<br>
Tyto služby se volají jen s App ID webu, bez přihlášeného uživatele. Data se načtou nejvýš jednou denně (a po uložení nastavení na stránce SkautIS → Jednotka).

<b>Profilová fotka ze SkautISu</b>
<br>
<b>OrganizationUnit -> PersonPhoto</b> - Načíst fotografii osoby (jen fotka přihlášeného uživatele, jen když si ji sám stáhne v profilu)
<br>
Volá se s přihlášeným uživatelem a jen pro jeho vlastní osobu (ID_Person z UserDetail).

<b>Jak nové funkce otestovat</b>
<br>
1. Nechte pro App ID webu povolit služby z verze 1.2.0 výše (služby z předchozích verzí musí být povolené také).
<br>
2. V `wp-config.php` zapněte `WP_DEBUG` a `WP_DEBUG_LOG`. Plugin pak zapisuje odpovědi SkautISu do `wp-content/debug.log` jako řádky „SkautIS integration – <služba>: …“.
<br>
3. Správa uživatelů: přepněte se na roli v akci, ve které jste v týmu (např. „VzA: vedoucí“), a otevřete Správu uživatelů. V tabulce mají být účastníci, instruktoři i vedení akce se sloupcem „Role v akci“. Do logu se zapíšou řádky InstructorAll / EventFunctionAll…
<br>
4. Pravidlo „Akce“: vytvořte pravidlo s akcí, nastavte ho stránce a vyzkoušejte možnosti „Kdokoli z akce“, „Tým akce“ a „Účastníci (bez týmu akce)“. Do logu se zapíšou řádky EventAllPerson a UserRoleAll.
<br>
5. Nábor a mapa: v menu SkautIS → Nábory vyplňte registrační číslo střediska nebo oddílu a uložte. Pod nastavením se ukáže, co se ze SkautISu načetlo. Na stránku vložte bloky „Nábor“ a „Mapa oddílů“. Do logu se zapíšou řádky UnitAll, AdvertisingDetail, AdvertisingCategoryAll, MeetingDateAll a OccupationAll.
<br>
6. Jednotka: v menu SkautIS → Jednotka vyplňte registrační číslo (nebo ho nechte prázdné, pak se použije číslo z náborů) a uložte. Pod nastavením se ukáže, co se ze SkautISu načetlo. Na stránku vložte blok „Jednotka“ a v jeho nastavení zvolte, co zobrazit. Do logu se zapíšou řádky UnitDetail, UnitContactAll, FunctionAllRegistry a AccountAll.
<br>
7. Profilová fotka: připojte svůj účet ke SkautISu, v profilu (Uživatelé → Profil) v části „Profilová fotka ze SkautISu“ klikněte na „Použít moji fotku ze SkautISu“. Fotka se ukáže jako profilový obrázek. Do logu se zapíše řádek PersonPhoto s názvy polí odpovědi a velikostí fotky (bez samotné fotky).

<b>Kontrola v administraci</b>
<br>
V menu SkautIS → Nastavení → záložka „Stav“ plugin zkontroluje verzi WordPressu a PHP, rozšíření PHP a spojení se SkautISem a otestuje všechny metody SkautISu, které používá. Metody jsou rozdělené podle funkcí a podle toho, jestli se volají bez přihlášení (jen s App ID), nebo s přihlášeným uživatelem. Ty druhé se otestují po kliknutí na „Přihlásit se do SkautISu a otestovat je“. Červený křížek znamená, že SkautIS metodu pro App ID odmítl a je potřeba ji nechat povolit.

<b>Jak poznat chybu v logu</b>
<br>
Řádek „SkautIS integration – <služba> (error): …“ znamená, že SkautIS volání odmítl:
<br>
- „Externí aplikace … nemá oprávnění k akci …“ – App ID webu nemá povolenou tuto službu. Je potřeba ji nechat povolit.
<br>
- „Nemáte oprávnění k akci … nad záznamem ID=…“ – App ID je v pořádku, ale aktuální role uživatele nemá přístup k té konkrétní akci (např. účastníky akce vidí jen její tým). Povolení App ID to nevyřeší, je potřeba se přepnout na roli s přístupem.
<br>
- „Uživatel nemá na zadanou roli oprávnění“ – přepnutí na roli, kterou uživatel nemá. Role zůstane původní.
