export {}

declare module 'vue-router' {
  interface RouteMeta {
    public?: boolean
    etape?: 'liquidation' | 'ordonnancement' | 'paiement'
  }
}
