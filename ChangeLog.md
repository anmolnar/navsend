### [1.1.6] 2023-05-12

  * Reportolás során a hálózati hiba ne legyen blokkoló: a számla kiállítás sikeres, a NavUpdater később próbálja újraküldeni.
  * Számla beküldést megszakítja, ha találunk folyamatban lévő technikai érvénytelenítést. (Invoice before annulment bug)
  * Technikai érvénytelenítést megszakítja, ha a számla még nem lett beküldve. (Annulment before invoice bug)

### [1.1.5] 2023-04-29

  * NAV API: „INVALID_LINE_OPERATION” kezelése: jóváíró számla esetén már nincs MODIFY a számla során
  * NavUpdater: annulment esetén a VERIFICATION_REJECTED is végstátusznak minősül és lezárja a workflow-t
  * NavUpdater: alapértelmezetten 5 percenként fusson

### [1.1.4] 2023-04-19

  * Kódok használata 0%-os ÁFÁ-hoz a megfelelő helyen: vatExemption, vatOutOfScope
  * Fix: harmadik országbéli (nem-EU) számlákat nem lehet hitelesíteni, mert az adószám hibásan a communityVatNumber mezőbe kerül
