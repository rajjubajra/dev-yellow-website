DESCRIPTION
-----------

Tailwind CSS V2.2 [ cli installation with JIT ]

source : https://youtu.be/DxcJbrs6rKk


Steps:
  1 - Setup run
   npx tailwindcss -o ./build/tailwind.css

  [ 
    This command creats default tailwind.css file with all the utilities 
    Follwing command scans all the defined purged html files and minmise css file with used css utilities only.
  ]

  2 - Purge html files
    npx tailwindcss -0 ./build/tailwind.css --jit --purge "./templates/**/*.html.twig ./templates/*.html.twig"
   
  3 - Watch process [while development in process]
    npx tailwindcss -0 ./build/tailwind.css --jit --purge "./templates/**/*.html.twig" -watch
    
  




