<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\suggestion_func;
use App\User;
use Illuminate\Http\Request;
use App\Genre;
use App\Book;
use App\user_book;
use App\user_genre as UG;
use App\Book_Genre as BG;
use App\Http\Controllers\functions;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class UserController extends Controller
{

    ////////////////////////////////////////////////////// Some Controllers about client and need to register and login!

    public function __construct()
    {
        $this->middleware('custom.auth');
    }

    ////////////////////////////////////////////      Books Controllers      ///////////////////////////////////////////

    public function buy_book($id)
    {
        $user_id = Auth::id();
        // Add Book:
        $new_data = [
            'book_id' => (int)$id ,
            'user_id' => $user_id
        ];
        $user_book = user_book::create($new_data);

        // Add Genres:
        functions::add_user_genre_type0($user_id, $id);

        if( ! $user_book instanceof user_book ){
            return redirect()->back()->with('sanitiza','مشکلی رخ داده، بعدا دوباره امتحان کنید');
        }
        return redirect()->back()->with('success', 'کتاب مورد نظر به لیست شما افزوده شد');

    }

    public function bought_books()
    {
        $user_id = Auth::id();
        $bought_books = User::with('books')->find($user_id);
        if ($bought_books != null)
            $bought_books = $bought_books->books;

        return view('bought_books',compact('bought_books'));
    }

    ////////////////////////////////////////////      Complete Register      ///////////////////////////////////////////

    public function complete_register()
    {
        return view('complete_register');
    }

    public function accept_complete_register()
    {
        $genres = Genre::all();
        return view('accept_complete_register',compact('genres'));
    }

    public function do_complete_register(Request $request)
    {
        if(functions::sanitize($request->input('books')) == 1){
            return redirect()->back()->with('sanitize', 'نام کتاب درست وارد نشده است');
        }
        $user_id = Auth::id();
        $books = $request->input('books');
        $books = explode("-",$books);
        // Add readed books Genres:
        foreach ($books  as $book){
            $book = Book::where('book_name',$book)->first();
            if ($book == null){
                // Call Scrapper
            }else{
                functions::add_user_genre_type0($user_id, $book->id);
            }
        }
        // Add liked genres:
        $i = 0;
        foreach ($request->all() as $req){
            if ( $i < 2 ){
                $i += 1;
            }
            else{
                try {
                    $past_data = UG::where('genre_id', $req)->where('user_id', $user_id)->first();
                    if ($past_data == null) {
                        $new_data = [
                            'genre_id' => intval($req),
                            'user_id' => $user_id,
                            'type' => 1,
                            'cnt' => 1
                        ];
                        $tmp = UG::create($new_data);
                    } else {
                        $new_cnt = $past_data->cnt + 1;
                        $past_data->update(['cnt' => $new_cnt]);
                    }
                } catch (\Exception $e) { // It's actually a QueryException but this works too
                    continue;
                }
            }
        }
        return redirect()->back()->with('success', 'ثبت نام شما تکمیل شد');
    }

    ////////////////////////////////////////////    suggestion Controller    ///////////////////////////////////////////

    public function suggestion(Request $request)
    {
        $user_id = Auth::id();
        $ugs = UG::where('user_id',$user_id)->get();

        // GenresScoreBoard Create:
        $genre_scores = suggestion_func::create_genre_score_borad($ugs);

        // BooksScoreBoard Create:
        $books = Book::all();
        $books = suggestion_func::calculate_books_score($books, $genre_scores);

        // sort book_scores array:
        $book_scores = suggestion_func::books_sort($books);

        // BooksSuggestions:
        return view('suggestions',compact('book_scores'));
    }


}
