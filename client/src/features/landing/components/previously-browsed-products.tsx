// import { ProductsListSkeleton } from '@/shared/components/ui/products-list-skeleton'
import type { ProductType } from '@/shared/types'
// import { Product } from '@/shared/components/product/product'

type PreviouslyBrowsedProductsProps = {
    products: ProductType[]
    loading: boolean
}

export default function PreviouslyBrowsedProducts({ products = [], loading }: PreviouslyBrowsedProductsProps) {
    const items = (products || []).slice(0, 20)

    if (!loading && items.length === 0) return null

    return null

    // (
    //     <div className="min-h-[467px] bg-white mt-10">
    //         <h3 className="my-2 select-none text-center font-extrabold text-xl sm:text-2xl md:text-3xl lg:text-4xl uppercase">
    //             <span className="text-black">Previously</span> <span className="text-[#E4041B]">browsed</span>
    //         </h3>
    //         <div className="flex min-h-[200px] w-[300px] items-center bg-white p-4">
    //             <div className="w-full">
    //                 {loading && items.length === 0 ? (
    //                     <ProductsListSkeleton />
    //                 ) : items.length === 0 && !loading ? (
    //                     <div className="p-6 text-center text-sm text-muted-foreground">No recently viewed products yet.</div>
    //                 ) : (

    //                     items.map((product) => (
    //                         <Product key={product.id} {...product} imageHeight={320} />
    //                     ))
    //                 )}
    //             </div>
    //         </div>
    //     </div>
    // )
}
