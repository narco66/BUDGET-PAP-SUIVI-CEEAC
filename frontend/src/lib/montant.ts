/** Affiche une chaîne décimale API sans la reconvertir en number IEEE-754 pour des totaux. */
export function formaterMontant(valeur: string | number | null | undefined, devise = 'XAF'): string {
  const brut = valeur == null ? '0.00' : String(valeur)
  const negatif = brut.startsWith('-')
  const [ent, dec = '00'] = (negatif ? brut.slice(1) : brut).split('.')
  const groupes = ent.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
  const corps = `${groupes},${dec.padEnd(2, '0').slice(0, 2)}`
  return `${negatif ? '-' : ''}${corps}\u00a0${devise}`
}

export function formaterTaux(valeur: string | number | null | undefined): string {
  const brut = valeur == null ? '0.00' : String(valeur)
  return `${brut.replace('.', ',')} %`
}
